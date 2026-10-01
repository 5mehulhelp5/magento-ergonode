# Ergonode_ProductPublisherAdminUi

## Responsibility boundary

Contributes the Send to Ergonode action and publication POST endpoint to the shared
ProductAdminUi workspace. Owns admin request handling and the publication configuration
presentation; ProductPublisher owns the outbound operation and persisted results.
No grid, catalog query, selection implementation or popup is owned here.

`PublishGridAction` checks `Ergonode_ProductPublisher::publish` and GraphQL write readiness.
Product publication has no REST login preflight; missing or expired REST credentials
do not open the shared login dialog before a product batch. The optional template
requirement check reports a warning after successful GraphQL publication when REST
is unavailable. Other actions retain their own REST preflight where required.
This module adds the shared dialog stylesheet to the product page for those actions.
The registration checks enabled Publisher modules before invoking its lazy provider.
`PublicationRequest` rechecks write readiness and delegates validated IDs to
`ProductPublicationBatchInterface`. Magento enforces POST form keys and controller ACL.
Disabling this module removes its action while the shared grid remains available.

## Dependencies and data

ProductAdminUi supplies selection and action contracts. ProductPublisher supplies
publication. PublisherAdminUi supplies write readiness and configuration presentation.
No owned database table. Publication configuration belongs to the runtime module.

## Verification

Unit tests cover readiness and delegation. Shared grid, batching and popup behavior
is tested in ProductAdminUi. Live external publication requires a selected test product.
