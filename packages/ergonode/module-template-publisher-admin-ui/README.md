# Ergonode_TemplatePublisherAdminUi

Optional publication actions extending Ergonode_TemplateAdminUi independently of
TemplateConsumer and TemplateConsumerAdminUi. Creates a template in Ergonode through
TemplatePublisher, then records the successfully published identity through Template.
It does not trigger import or synchronization. The base workspace saves the user's
mapping through the neutral Template contract. Existing role ACL IDs are retained.
