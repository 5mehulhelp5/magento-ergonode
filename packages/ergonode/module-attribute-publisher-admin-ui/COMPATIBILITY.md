# Attribute compatibility in publication editors

Attribute owns generic type compatibility through AttributeTypeCompatibilityInterface.
AttributePublisherAdminUi injects that API into AttributePublisherMapping and passes
`attribute_compatibility` to its JavaScript initializer. This is a direct code dependency
on Attribute, already available through the AttributePublisher Composer dependency.
JavaScript applies the supplied rules and the publication capability filter; it owns
neither a second compatibility table nor snapshot storage or mutation transport.

Product/category publication adapters continue to own routes and mapping persistence.
The compatibility payload is local page configuration, not a new saved setting or a
data migration. Storybook fixtures supply representative maps through the same input.
