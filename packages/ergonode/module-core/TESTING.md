# Testing Ergonode_Core

Run the module gate from the backend repository root:

```bash
make -f .agents/backend/Makefile module-check module=Ergonode_Core
```

The module gate covers the transport guards and shared import infrastructure.
Domain smoke scenarios belong to the owning consumer and publisher modules.
