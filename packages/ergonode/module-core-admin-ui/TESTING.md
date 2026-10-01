# Testing Ergonode_CoreAdminUi

Run:

```bash
make -f .agents/backend/Makefile module-check module=Ergonode_CoreAdminUi
```

Storybook infrastructure and shared UI contract:

```bash
node --test app/code/Ergonode/CoreAdminUi/Test/Js/storybook-contract.test.cjs
ddev exec npm run storybook:ergonode:build
```

Admin smoke checks:

1. disable Category Sync and save a valid GraphQL URL/API key;
2. remove all Category Sync Profiles and save the GraphQL settings again;
3. confirm both saves complete without category-profile validation.
4. confirm Ergonode Updates is disabled by default and exposes only its own
   enable flag and encrypted API key.
