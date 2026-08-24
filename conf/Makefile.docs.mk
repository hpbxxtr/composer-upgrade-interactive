#-- h:ui
#--- docs
DOCS_DIR ?= ${PWD}/docs
NPM ?= npm --prefix ${DOCS_DIR}

#-- h:ui
#--- docs
docs-install: #~~ installs the documentation site dependencies (npm ci)
	${NPM} ci

docs-node-modules: #~~ installs the documentation dependencies when they are missing #v
	@[ -d "${DOCS_DIR}/node_modules" ] || make -s docs-install

docs-dev: docs-node-modules #~~ serves the documentation site locally with live reload
	${NPM} run docs:dev

docs-build: docs-node-modules #~~ builds the static documentation site (fails on dead links)
	${NPM} run docs:build

docs-preview: docs-build #~~ serves the built documentation site
	${NPM} run docs:preview

docs-clean: #~~ removes the documentation build output and cache
	rm -rf ${DOCS_DIR}/.vitepress/dist ${DOCS_DIR}/.vitepress/cache

docs: docs-build #~~ builds the documentation site
