'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const mediaRoot = path.resolve(moduleRoot, '../module-product-media');
const systemXml = fs.readFileSync(
    path.join(moduleRoot, 'etc/adminhtml/system.xml'),
    'utf8'
);
const defaultConfigXml = fs.readFileSync(
    path.join(mediaRoot, 'etc/config.xml'),
    'utf8'
);
const galleryConfiguration = fs.readFileSync(
    path.join(mediaRoot, 'Api/GalleryConfigurationInterface.php'),
    'utf8'
);

test('gallery selection is required and global alongside the synchronization switch', () => {
    assert.match(systemXml, /<group id="media"[\s\S]*?<label>Media<\/label>/);
    assert.match(systemXml, /<field id="synchronization_enabled"[^>]*type="select"[\s\S]*?Magento\\Config\\Model\\Config\\Source\\Yesno/);
    const field = systemXml.match(/<field id="gallery_attribute"[\s\S]*?<\/field>/)[0];
    assert.match(field, /showInDefault="1" showInWebsite="0" showInStore="0"/);
    assert.match(field, /<validate>required-entry<\/validate>/);
    assert.match(field, /<backend_model>Ergonode\\MediaAdminUi\\Model\\Config\\Backend\\GalleryAttribute/);
    assert.match(galleryConfiguration, /getGalleryAttributeCode/);
    assert.doesNotMatch(galleryConfiguration, /ERGONODE_ATTRIBUTE_CODE/);
    assert.doesNotMatch(defaultConfigXml, /<gallery_attribute>/);
});
