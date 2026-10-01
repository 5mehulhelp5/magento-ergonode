# Ergonode_TemplateAttributeConsumerAdminUi

Owns inbound template-structure configuration and the optional manual-placement controls contributed to TemplateAttributeAdminUi. It uses the Consumer ManualPlacementSaverInterface through an authenticated admin POST with template-save ACL and form-key validation. It owns no tables, group editing UI or synchronization algorithm.

Depends on TemplateAttributeAdminUi to enrich its popup; neutral presentation and Publisher remain independent. The button appears only for mapped attributes already in the set. System-protected attributes are visibly pinned and cannot be unlocked. User-controlled pins save per set, keep values synchronizing, and preserve membership after source removal. Errors leave the displayed saved state unchanged.
