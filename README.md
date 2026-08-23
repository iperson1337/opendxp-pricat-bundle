# OpenDXP PRICAT Bundle

`iperson1337/opendxp-pricat-bundle` — import of EDI **PRICAT** (price catalogue) XML files into
OpenDXP DataObjects and an admin UI to reconcile every catalogue line against the matching product.

What it does:

- **Import** a PRICAT XML (admin upload or `bin/console pricat:import file.xml`) into one `Pricat`
  object plus one `PricatPosition` child per `<POSITION>`, inside a single DB transaction.
  XML tags are mapped to fields **by name** (case-insensitive) from the class definition, so new
  tags/fields need no code — only an alias when the names differ.
- **Compare** a position with the product it refers to (barcode lookup by default) in a modal:
  configurable list of position-field ↔ product-field pairs, differences highlighted.
- **Accept** (copy the *selected* non-empty, differing values of writable pairs onto the product, mark
  the position processed + confirmed) or **decline** (store a comment, mark processed + declined).
  The compare window preselects every writable row whose PRICAT value differs; the reviewer can untick
  rows before accepting, or accept with nothing selected (position closed, product untouched).
- Positions tab: header with the pricat's key data and live counters (total / pending / accepted /
  declined), status filter (pending by default). The pricat list shows the same counters per row.

## Requirements

- OpenDXP `^1.0` with the classic admin bundle (`open-dxp/admin-bundle`).
- DataObject classes `Pricat` and `PricatPosition`. The installer creates them from
  `install/class_Pricat_export.json` / `install/class_PricatPosition_export.json` **when they do not exist
  yet**; an application that already owns the classes keeps its own definitions untouched
  (`var/classes/definition_*.php` stays the source of truth). The bundle is typed against the generated
  `OpenDxp\Model\DataObject\Pricat` / `PricatPosition` classes. If you bring your own definitions,
  `PricatPosition` must have a `Pricat` many-to-one relation, `processed`, `confirmation` and
  `rejectionComment` fields; everything else is mapped by name.
- The admin permission (`iperson1337_opendxp_pricat.permission`, `pricat_bundle` by default) is created
  by the installer as well — run `bin/console opendxp:bundle:install OpenDxpPricatBundle` once (idempotent).

## Installation

```bash
composer require iperson1337/opendxp-pricat-bundle
```

`config/bundles.php`:

```php
Iperson1337\OpenDxpPricatBundle\OpenDxpPricatBundle::class => ['all' => true],
```

Then:

```bash
bin/console cache:clear
bin/console opendxp:bundle:install OpenDxpPricatBundle   # classes (if missing) + admin permission, idempotent
bin/console opendxp:build:classes   # generate the PHP classes when the installer created the definitions
bin/console assets:install          # publishes public/ to /bundles/opendxppricat/
bin/console fos:js-routing:dump     # routes are exposed for Routing.generate() in the admin JS
```

To refresh the shipped exports after changing the definitions in a project, regenerate them with
`ClassDefinition\Service::generateClassDefinitionJson()` (the same JSON the admin "Export" button
produces) and drop `modificationDate` from the files.

Routes (`config/opendxp/routing.yaml`) are picked up automatically by OpenDXP's bundle routing loader;
no entry in the application's `config/routes.yaml` is needed.

## Configuration

Every key has a default that reproduces the behaviour of the original hard-coded bundle, so an empty
`config/packages/iperson1337_opendxp_pricat.yaml` is valid. Reference:

```bash
bin/console config:dump-reference iperson1337_opendxp_pricat
bin/console debug:config iperson1337_opendxp_pricat
```

```yaml
iperson1337_opendxp_pricat:
    permission: pricat_bundle          # admin permission key (menu + every endpoint); created by the installer
    folder_path: '/Прикаты'            # DataObject folder for imported Pricat objects
    date_format: 'Y-m-d'               # <DATE>/<DATEFROM>/<DATETO>; lenient parsing as fallback
    duplicate_check_fields: [number, action, date, docFlowId]

    xml:
        pricat_field_aliases: {}       # XML tag => Pricat field when names differ
        position_field_aliases:        # XML tag => PricatPosition field when names differ
            POSITIONNUMBER: positonNumber
            DAYSUNTILSPOIL: bestBeforDate
        ignored_tags: [CURRENCY]       # known tags that are intentionally not stored

    product:
        class: Product
        lookup_field: barcode          # product field matched against the position value
        position_lookup_field: product # position field holding the product identifier
        include_unpublished: false
        version_note: 'PRICAT: позиция принята'
        mapping:                       # compare-window rows; `writable: false` = compare only
            - { position: productName, product: fullName }
            - { position: brand, product: legacyProductBrand, accessor: relation_name, writable: false }
            # ...

    list:
        page_size: 25
```

### Mapping semantics

| Setting | Behaviour |
|---------|-----------|
| Tag → field resolution | alias (`*_field_aliases`) → field whose name equals the tag case-insensitively → unmapped (logged at `info`, returned by the import, never an error). |
| Value casting | derived from the field definition: `numeric` → `int` / `float` / decimal `string` (exactly what the generated setter expects), `date`/`datetime` → `Carbon`, `booleanSelect`/`checkbox` → `bool` (`1/0/true/false/yes/no/да/нет`), everything else → trimmed string. Unparsable values become `null`. |
| `accessor: field` | read/write the product field itself; `quantityValue` fields read their numeric value and keep (or create with the field's default unit) the unit on write; localized fields use the default language. |
| `accessor: relation_name` | read the `name` of the object a `manyToOneRelation` points to. Read-only — configuration validation rejects `writable: true`. |
| Accept | for each `writable` pair (optionally narrowed by the request's `fields`): skip when the position value is empty or equal (after normalisation) to the product value, otherwise write. Product is saved only when at least one field changed; the position is always marked `processed=true, confirmation=true`. A requested field outside the writable mapping is an error, never silently skipped. |
| Duplicate check | a Pricat whose `duplicate_check_fields` values all match an existing object is rejected (`DuplicatePricatException`). |

### Exposed settings for the admin JS

`AdminSettingsSubscriber` publishes `opendxp.settings.pricat = {permission, pageSize}` so the menu
guard and grid page size follow the configuration.

## Endpoints

All routes require the configured permission (`UserAwareController::checkPermission`) and answer
`{success, message, data[, total]}`; application-level errors come back as `success: false` with a
translated `message` (domain `admin`, user language).

| Route name | Method | Path |
|------------|--------|------|
| `iperson1337_opendxp_pricat_list` | GET | `/admin/pricat/list?start=&limit=` |
| `iperson1337_opendxp_pricat_show` | GET | `/admin/pricat/{id}` — header row + counters (`positionCount`, `pendingCount`, `acceptedCount`, `declinedCount`) |
| `iperson1337_opendxp_pricat_positions` | GET | `/admin/pricat/{id}/positions?start=&limit=&filter=` — `filter` = `all` (default), `pending`, `processed`, `accepted`, `declined` |
| `iperson1337_opendxp_pricat_import` | POST | `/admin/pricat/import` (multipart `file`) |
| `iperson1337_opendxp_pricat_position_compare` | GET | `/admin/pricat/position/{id}/compare` |
| `iperson1337_opendxp_pricat_position_accept` | POST | `/admin/pricat/position/{id}/accept` — optional JSON body `{"fields": ["fullName", "width"]}`: product fields to write; key absent = every writable mapping, `[]` = write nothing |
| `iperson1337_opendxp_pricat_position_decline` | POST | `/admin/pricat/position/{id}/decline` (`comment`) |

## CLI

```bash
bin/console pricat:import path/to/pricat_1.xml [path/to/pricat_2.xml ...]
```

## Code map

```
src/
├── OpenDxpPricatBundle.php                 # JS/CSS asset registration, installer hook
├── Installer.php                           # opendxp:bundle:install → classes (if missing) + admin permission
install/class_{Pricat,PricatPosition}_export.json  # class definitions for fresh installations
├── DependencyInjection/                    # Configuration (defaults) + extension
├── Model/                                  # PricatConfig, ProductFieldMapping, ParsedPricat, CompareRow, ImportResult
├── Enum/                                   # PricatAction, PositionAction (codes → translation keys), PositionFilter
├── Exception/                              # PricatException (+ translation key) and subclasses
├── Service/Xml/PricatXmlParser.php         # XML → ParsedPricat (pure, no Pimcore)
├── Service/Import/                         # FieldValueCaster, DataObjectHydrator, PricatDuplicateFinder, PricatImporter
├── Service/Product/                        # ProductFinder, DataObjectFieldAccessor
├── Service/Compare/                        # ValueNormalizer, PositionProductComparator, PositionAcceptor, PositionDecliner, FieldLabelProvider
├── Service/Position/PositionStatsRepository.php  # per-pricat counters (one grouped read query)
├── Controller/                             # PricatController, PositionController
├── Command/ImportPricatCommand.php         # pricat:import
└── EventSubscriber/AdminSettingsSubscriber.php
public/js/opendxp/                          # startup (menu), pricatPanel, positionsPanel, compareWindow, importWindow, models
translations/admin.{ru,en}.yml              # plugin_pimcore_pricat_* keys
```

## License

MIT
