#!/bin/bash
set -e

# Dual Sabberworm Version Test Script
# Tests horde/css_parser with both Sabberworm 8.9 and 9.3
# to verify compatibility layer works correctly

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results tracking
SABBERWORM_8_PASSED=0
SABBERWORM_8_FAILED=0
SABBERWORM_9_PASSED=0
SABBERWORM_9_FAILED=0

echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}Horde Css_Parser Dual Version Test Suite${NC}"
echo -e "${BLUE}============================================${NC}"
echo ""

# Check PHP version
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo -e "${BLUE}PHP Version:${NC} $PHP_VERSION"
echo ""

# Function to run tests and capture results
run_tests() {
    local test_suite=$1
    local test_name=$2

    echo -e "${YELLOW}Running: $test_name${NC}"

    if [ "$test_suite" = "all" ]; then
        # Run all tests (legacy + modern)
        OUTPUT=$(phpunit --testdox --colors=never 2>&1)
    else
        # Run only specific test suite
        OUTPUT=$(phpunit --testdox --colors=never --testsuite="$test_suite" 2>&1)
    fi

    # Check exit status
    RESULT=$?

    # Extract test counts
    TOTAL_TESTS=$(echo "$OUTPUT" | grep -oP 'Tests: \K[0-9]+' | head -1 || echo "0")
    ASSERTIONS=$(echo "$OUTPUT" | grep -oP 'Assertions: \K[0-9]+' | head -1 || echo "0")
    ERRORS=$(echo "$OUTPUT" | grep -oP 'Errors: \K[0-9]+' | head -1 || echo "0")
    FAILURES=$(echo "$OUTPUT" | grep -oP 'Failures: \K[0-9]+' | head -1 || echo "0")

    # Calculate passed tests
    FAILED=$((ERRORS + FAILURES))
    PASSED=$((TOTAL_TESTS - FAILED))

    # Print summary
    if [ $RESULT -eq 0 ]; then
        echo -e "  ${GREEN}✓ PASS${NC} - Tests: $TOTAL_TESTS, Assertions: $ASSERTIONS"
        return 0
    else
        echo -e "  ${RED}✗ FAIL${NC} - Tests: $TOTAL_TESTS, Passed: $PASSED, Failed: $FAILED"
        if [ $ERRORS -gt 0 ]; then
            echo -e "    Errors: $ERRORS"
        fi
        if [ $FAILURES -gt 0 ]; then
            echo -e "    Failures: $FAILURES"
        fi

        # Show failed test details
        echo "$OUTPUT" | grep -A 3 "✘" | head -20

        return 1
    fi
}

# Function to get installed Sabberworm version
get_sabberworm_version() {
    composer show sabberworm/php-css-parser 2>/dev/null | grep -oP 'versions : \* \K[^ ]+' || echo "unknown"
}

echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}Phase 1: Testing with Sabberworm 8.9${NC}"
echo -e "${BLUE}============================================${NC}"
echo ""

# Force install Sabberworm 8.9
echo -e "${YELLOW}Installing Sabberworm 8.9...${NC}"
composer require sabberworm/php-css-parser:^8.9 --quiet --no-interaction 2>&1 | tail -3
INSTALLED_VERSION=$(get_sabberworm_version)
echo -e "${GREEN}Installed: $INSTALLED_VERSION${NC}"
echo ""

# Run all tests with Sabberworm 8.9 (including legacy API)
echo -e "${YELLOW}Running ALL tests (legacy lib/ + modern src/)...${NC}"
echo ""

if run_tests "all" "All Tests (8.9)"; then
    SABBERWORM_8_STATUS="PASS"
    SABBERWORM_8_STYLE="${GREEN}"
else
    SABBERWORM_8_STATUS="FAIL"
    SABBERWORM_8_STYLE="${RED}"
fi

echo ""

# Get detailed test results for 8.9
echo -e "${YELLOW}Test breakdown:${NC}"
phpunit --testdox --colors=never 2>&1 | grep -E "^(Import|Parser|Security|Selector|Url|Value)" | while read line; do
    echo "  $line"
done
echo ""

echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}Phase 2: Testing with Sabberworm 9.3${NC}"
echo -e "${BLUE}============================================${NC}"
echo ""

# Force install Sabberworm 9.3
echo -e "${YELLOW}Installing Sabberworm 9.3...${NC}"
composer require sabberworm/php-css-parser:^9.3 --quiet --no-interaction 2>&1 | tail -3
INSTALLED_VERSION=$(get_sabberworm_version)
echo -e "${GREEN}Installed: $INSTALLED_VERSION${NC}"
echo ""

# Run only modern tests with Sabberworm 9.x (exclude legacy lib/)
echo -e "${YELLOW}Running MODERN tests only (src/ - excluding legacy lib/)...${NC}"
echo ""

# Create temporary phpunit config that excludes legacy tests
cat > phpunit-modern-only.xml << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/13.0/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         cacheDirectory="build/cache/phpunit"
         executionOrder="depends,defects"
         beStrictAboutOutputDuringTests="true"
         beStrictAboutCoverageMetadata="true"
         displayDetailsOnIncompleteTests="true"
         displayDetailsOnSkippedTests="true"
         displayDetailsOnTestsThatTriggerErrors="true"
         displayDetailsOnTestsThatTriggerWarnings="true"
         displayDetailsOnTestsThatTriggerNotices="true"
         displayDetailsOnTestsThatTriggerDeprecations="true"
         failOnWarning="false"
         failOnRisky="false">
    <testsuites>
        <testsuite name="unit">
            <directory>test/unit</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </source>
</phpunit>
EOF

# Run modern tests only
OUTPUT_9=$(phpunit --testdox --colors=never -c phpunit-modern-only.xml 2>&1)
RESULT_9=$?

echo "$OUTPUT_9" | grep -E "(Tests:|Assertions:|OK|ERRORS|FAILURES)" || true
echo ""

if [ $RESULT_9 -eq 0 ]; then
    SABBERWORM_9_STATUS="PASS"
    SABBERWORM_9_STYLE="${GREEN}"
else
    SABBERWORM_9_STATUS="FAIL"
    SABBERWORM_9_STYLE="${RED}"

    # Show failures
    echo -e "${RED}Failed tests:${NC}"
    echo "$OUTPUT_9" | grep -A 3 "✘" | head -30
    echo ""
fi

# Get detailed test results for 9.x
echo -e "${YELLOW}Test breakdown:${NC}"
echo "$OUTPUT_9" | grep -E "^(Import|Parser|Security|Selector|Url|Value)" | while read line; do
    echo "  $line"
done
echo ""

# Check legacy tests fail as expected
echo -e "${YELLOW}Verifying legacy tests fail (as expected)...${NC}"
LEGACY_OUTPUT=$(phpunit --testdox --colors=never test/Horde/Css/Parser/ParserTest.php 2>&1 || true)
LEGACY_ERRORS=$(echo "$LEGACY_OUTPUT" | grep -oP 'Errors: \K[0-9]+' | head -1 || echo "0")

if [ "$LEGACY_ERRORS" -gt 0 ]; then
    echo -e "  ${GREEN}✓ EXPECTED${NC} - Legacy lib/ tests fail with Sabberworm 9.x (Errors: $LEGACY_ERRORS)"
else
    echo -e "  ${YELLOW}⚠ WARNING${NC} - Legacy tests didn't fail as expected"
fi
echo ""

# Cleanup temporary config
rm -f phpunit-modern-only.xml

echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}Final Report${NC}"
echo -e "${BLUE}============================================${NC}"
echo ""

echo -e "${BLUE}Sabberworm 8.9 (All Tests):${NC}"
echo -e "  Status: ${SABBERWORM_8_STYLE}${SABBERWORM_8_STATUS}${NC}"
echo ""

echo -e "${BLUE}Sabberworm 9.3 (Modern src/ Only):${NC}"
echo -e "  Status: ${SABBERWORM_9_STYLE}${SABBERWORM_9_STATUS}${NC}"
echo ""

# Overall assessment
echo -e "${BLUE}Assessment:${NC}"

if [ "$SABBERWORM_8_STATUS" = "PASS" ] && [ "$SABBERWORM_9_STATUS" = "PASS" ]; then
    echo -e "  ${GREEN}✓ SUCCESS${NC} - Dual version support working correctly!"
    echo -e "  ${GREEN}✓${NC} Modern API works with both Sabberworm 8.9 and 9.3"
    echo -e "  ${GREEN}✓${NC} Legacy API works with Sabberworm 8.9"
    echo -e "  ${GREEN}✓${NC} Legacy API correctly fails with Sabberworm 9.3 (as documented)"
    EXIT_CODE=0
elif [ "$SABBERWORM_8_STATUS" = "PASS" ] && [ "$SABBERWORM_9_STATUS" = "FAIL" ]; then
    echo -e "  ${RED}✗ FAILURE${NC} - Modern API broken with Sabberworm 9.3!"
    echo -e "  ${GREEN}✓${NC} Sabberworm 8.9 works"
    echo -e "  ${RED}✗${NC} Sabberworm 9.3 has failures in modern API"
    echo ""
    echo -e "  ${RED}Action Required:${NC} Fix compatibility issues with Sabberworm 9.3"
    EXIT_CODE=1
elif [ "$SABBERWORM_8_STATUS" = "FAIL" ] && [ "$SABBERWORM_9_STATUS" = "PASS" ]; then
    echo -e "  ${RED}✗ FAILURE${NC} - Sabberworm 8.9 support broken!"
    echo -e "  ${RED}✗${NC} Sabberworm 8.9 has failures"
    echo -e "  ${GREEN}✓${NC} Sabberworm 9.3 works"
    echo ""
    echo -e "  ${RED}Action Required:${NC} Fix compatibility issues with Sabberworm 8.9"
    EXIT_CODE=1
else
    echo -e "  ${RED}✗ FAILURE${NC} - Both versions broken!"
    echo -e "  ${RED}✗${NC} Sabberworm 8.9 has failures"
    echo -e "  ${RED}✗${NC} Sabberworm 9.3 has failures"
    echo ""
    echo -e "  ${RED}Action Required:${NC} Fix compatibility layer implementation"
    EXIT_CODE=1
fi

echo ""
echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}Documentation${NC}"
echo -e "${BLUE}============================================${NC}"
echo ""
echo -e "Migration Guide: ${GREEN}doc/SABBERWORM_COMPATIBILITY.md${NC}"
echo -e "Changelog: ${GREEN}doc/changelog.yml${NC}"
echo ""
echo -e "${BLUE}Composer Constraint:${NC}"
composer show sabberworm/php-css-parser 2>/dev/null | grep "versions" || true
echo ""

exit $EXIT_CODE
