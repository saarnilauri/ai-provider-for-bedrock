.PHONY: dist clean-dist deploy-dev

dist:
	./scripts/build-plugin-zip.sh

clean-dist:
	rm -rf dist

# Sync a production build (no dev dependencies) to the local dev site.
deploy-dev: dist
	rsync -a --delete dist/ai-provider-for-bedrock/ \
		/Users/laurisaarni/valu-playbooks-81/sites/gutenbrain-api/web/wp-content/plugins/ai-provider-for-bedrock/
	@echo "Deployed to gutenbrain-api."
