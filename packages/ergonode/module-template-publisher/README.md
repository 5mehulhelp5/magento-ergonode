# Ergonode_TemplatePublisher

This module owns base Ergonode template publication through GraphQL mutations.
It creates the template identity used by the template-to-attribute-set mapping.

## Scope

- create an Ergonode template with stable code and localized names;
- execute publication through the shared publisher infrastructure;
- expose a contract used by the optional admin action.

It does not know about Ergonode sections, template attributes, Magento
attribute groups or attribute placement. It does not publish template structure. Product publication no longer reads
template completeness requirements through REST.

The module does not persist mappings. `Ergonode_Template` owns neutral mapping
persistence and recording published identities; import remains optional in TemplateConsumer.

See [PLAN.md](PLAN.md) for the implementation sequence and schema gate.
