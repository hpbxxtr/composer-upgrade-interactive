#-- h:ui
#--- test
PHP_PEST ?= ${PWD}/vendor/bin/pest

#-- h:ui
#--- test
pest: #~~ runs php tests with pest
	${PHP_PEST}

pest-coverage: #~~ runs php tests with pest
	XDEBUG_MODE=coverage ${PHP_PEST} --coverage

test: pest #~~ runs all tests