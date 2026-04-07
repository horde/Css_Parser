# Sabberworm Version Compatibility

This library supports both Sabberworm 8.x and 9.x via runtime autodetection.

## Modern API (src/Parser.php)

**Fully compatible with both versions:**
- Sabberworm 8.9+ (PHP 5.4+)
- Sabberworm 9.0+ (PHP 7.2+)

The modern PSR-4 facade (`Horde\Css\Parser\Parser`) automatically detects which
version is installed and adapts method calls accordingly. No code changes needed
when switching between Sabberworm versions.

### How It Works

The parser uses runtime detection to handle API differences:

1. **Class detection** - Checks for `Sabberworm\CSS\Property\Declaration` (9.2+ only)
2. **Method detection** - Uses `method_exists()` to call correct methods
3. **Structural adaptation** - Handles `DeclarationBlock` changes in 9.0

This means your code works identically with both versions:

```php
use Horde\Css\Parser\Parser;

$parser = new Parser($css);
$safe = $parser->removeImports()
              ->removeUrlRules()
              ->removeRulesByName('cursor');
$output = $safe->compress();

// Works with Sabberworm 8.9 OR 9.3 - no changes needed
```

## Legacy API (lib/Horde/Css/Parser.php)

**DEPRECATED and version-locked:**
- Only supports Sabberworm 8.x
- Exposes Sabberworm objects directly via public properties
- *May break with PHP 8.5** unless Sabberworm 8.x adds PHP 8.5 support

The legacy API (`Horde_Css_Parser`) exposes Sabberworm's internal `Document`
and `Parser` objects directly. This means:

- It does not adapt to Sabberworm 9.x API changes
- Advanced users accessing `$parser->doc` directly will see breaking changes
- PHP 8.5 compatibility depends on Sabberworm 8.x adding PHP 8.5 support

**Recommendation:** Migrate to modern API (`Horde\Css\Parser\Parser`).

## PHP Version Requirements

| Horde Css_Parser | PHP Required | Sabberworm Supported |
|------------------|--------------|----------------------|
| 2.x (modern API) | ^8.0         | 8.9+ OR 9.0+        |
| 2.x (legacy API) | ^8.0         | 8.9+ only           |

## PHP 8.5 Compatibility

### Modern API (Recommended)

Will work with PHP 8.5 if either:
- Sabberworm 8.x adds PHP 8.5 support, OR
- You upgrade to Sabberworm 9.x (which already supports modern PHP)

The modern API abstracts all version differences, so upgrading Sabberworm
is transparent to your code.

### Legacy API (Deprecated)

Will break with PHP 8.5 unless:
- Sabberworm 8.x adds PHP 8.5 support (unlikely given 9.x exists)

If you're using the legacy API and need PHP 8.5, you must:
1. Upgrade to Sabberworm 9.x manually, AND
2. Fix any direct `$parser->doc` usage that breaks

Better solution: Migrate to modern API now.

## Choosing a Sabberworm Version

### Use Sabberworm 8.9 if:
- You need the legacy API (`Horde_Css_Parser`)
- You're on PHP 8.0-8.4
- You want maximum stability

### Use Sabberworm 9.3 if:
- You need PHP 8.5 support
- You want latest CSS features (`@layer`, etc.)
- You prefer stricter type safety
- You're starting new code

### Either Works if:
- You use the modern API (`Horde\Css\Parser\Parser`)
- You're on PHP 8.0-8.4
- You don't need cutting-edge CSS features

## Getting Help

- **Modern API issues:** Report to Horde Css_Parser
- **Sabberworm compatibility:** Report to Horde Css_Parser
- **Legacy API issues:** Consider migrating to modern API
- **Sabberworm bugs:** Report upstream to sabberworm/php-css-parser

## See Also

- [Sabberworm PHP-CSS-Parser](https://github.com/MyIntervals/PHP-CSS-Parser)
- [Sabberworm Release Notes](https://github.com/MyIntervals/PHP-CSS-Parser/releases)
- Horde Css_Parser changelog: `doc/changelog.yml`
