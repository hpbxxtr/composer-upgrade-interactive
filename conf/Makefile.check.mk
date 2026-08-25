#-- h:ui
#--- check
RECTOR_OUTPUT_FILE := ./.cache/rector.last-output.txt
PHP_CS_FIX_OUTPUT_FILE := ./.cache/php-cs-fixer.last-output.txt
PHPSTAN_OUTPUT_FILE := ./.cache/phpstan.last-output.txt

#-- h:ui
#--- check
rector-group-by-rule: #~~ runs rector dry-run and groups applied rules by count (sorted by count)
	@make -s rector-dry-run 2>&1 \
		| grep -oE '^ \* \S+' \
		| tr -d ' *' \
		| sort \
		| uniq -c \
		| sort -rn

php-cs-fix-group-by-rule: #~~ runs php-cs-fixer dry-run and groups violations by rule (sorted by count)
	@make -s php-cs-fix-dry-run 2>&1 \
		| grep -oE '\([^\%)]+\)' \
		| tr -d '()' \
		| tr ',' '\n' \
		| tr -d ' ' \
		| sort \
		| uniq -c \
		| sort -rn

phpstan-group-by-rule: #~~ runs phpstan and groups errors by rule identifier (sorted by count)
	@make -s phpstan > ${PHPSTAN_OUTPUT_FILE} || true
	@cat ${PHPSTAN_OUTPUT_FILE} \
		| grep '^Identifier: ' \
		| sed 's/^Identifier: //' \
		| sort \
		| uniq -c \
		| sort -rn

composer-audit: #~~ runs composer audit
	${PHP_COMPOSER} audit

define print_section
	@printf "\n\n"
	@printf "$(call text,## $(1),${COLOR_SECONDARY},underline)"
	@printf "\n\n"
endef

check-rector: #~~ runs rector in dry-run mode to check for possible code refactorings
	$(call print_section,RUN PHP RECTOR)
	@make -s rector-dry-run || true

check-rector-fix: #~~ runs rector for possible code refactorings
	$(call print_section,RUN PHP RECTOR)
	@make -s rector || true

check-php-cs: #~~ runs php-cs-fixer in dry-run mode to check for possible code style issues
	$(call print_section,RUN PHP CODE STYLE)
	@make -s php-cs-fixer-dry-run || true

check-php-cs-fix: #~~ runs php-cs-fixer in dry-run mode to check for possible code style issues
	$(call print_section,RUN PHP CODE STYLE --fix)
	@make -s php-cs-fixer || true

check-phpstan: #~~ runs phpstan to check for possible code issues
	$(call print_section,RUN PHPSTAN)
	@make -s phpstan || true

check-php: check-rector check-php-cs check-phpstan #~~ checks code style and static analysis on php scope

check-php-fix: check-rector-fix check-php-cs-fix check-phpstan #~~ checks and fixes code style and static analysis on php scope

check-composer-audit: #~~ runs composer audit to check for vulnerable composer dependencies
	$(call print_section,RUN COMPOSER AUDIT)
	@make -s composer-audit || true

check-dependencies: check-composer-audit #~~ runs dependency checks locally

check: check-php check-dependencies #~~ runs all code style, static analysis and security checks

check-fix: check-php-fix check-dependencies #~~ runs all code style, static analysis and security checks and fixes what can be fixed

check-group-by-rule: #~~ runs all checks and groups results by rule
	$(call print_section, RUN RECTOR GROUPED BY RULE)
	@make -s rector-group-by-rule ||true
	$(call print_section, RUN PHP-CS-FIX GROUPED BY RULE)
	@make -s php-cs-fix-group-by-rule ||true
	$(call print_section, RUN PHPSTAN GROUPED BY RULE)
	@make -s phpstan-group-by-rule ||true
