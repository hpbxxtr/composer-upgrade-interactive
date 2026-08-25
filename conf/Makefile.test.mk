#-- h:ui
#--- test
PHP_PEST ?= ${PWD}/vendor/bin/pest

# Supported PHP versions for `test-compatibility-matrix`.
# Keep in sync with composer.json `require.php`, .github/workflows/_test.yml
# and the Requirements section in README.md.
COMPATIBILITY_MATRIX = 8.3 8.4 8.5

#-- h:ui
#--- test
pest: #~~ runs php tests with pest
	${PHP_PEST}

pest-coverage: #~~ runs php tests with pest with coverage report
	${PHP_PEST_COVERAGE} --coverage

test: pest #~~ runs all tests

test-compatibility: #~~ runs tests on a specific php version, e.g. `make test-compatibility php=8.4`
	PHP_VERSION=$(php) docker compose -f compose.test.yaml run --rm compatibility-test

test-compatibility-matrix: #~~ runs tests for all supported php versions
	@exit_code=0; \
	for version in $(COMPATIBILITY_MATRIX); do \
	  echo ""; \
	  echo "========================================"; \
	  echo "  Testing PHP $$version"; \
	  echo "========================================"; \
	  PHP_VERSION=$$version \
	    docker compose -f compose.test.yaml run --rm compatibility-test \
	    || exit_code=1; \
	done; \
	exit $$exit_code
