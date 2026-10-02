.DEFAULT_GOAL := help
.PHONY: help quality module-check module-static module-done tooling-tests
help:
	@./quality help
quality:
	@./quality all
module-check module-static module-done:
	@./quality $@ "$(module)" $(args)
tooling-tests:
	@./quality tooling-tests
