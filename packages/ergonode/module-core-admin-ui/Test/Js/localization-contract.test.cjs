'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {moduleDirectories, modulePath} = require('./module-paths.cjs');

const adminRoots = moduleDirectories().filter((root) => /(?:AdminUi|admin-ui)$/.test(root));

function filesUnder(root) {
    return fs.readdirSync(root, {withFileTypes: true}).flatMap((entry) => {
        const entryPath = path.join(root, entry.name);

        if (entry.isDirectory()) {
            return entry.name === 'Test' || entry.name === 'i18n' ? [] : filesUnder(entryPath);
        }

        return /\.(?:html|js|php|phtml|xml)$/.test(entry.name) ? [entryPath] : [];
    });
}

function csvKeys(locale) {
    const localePaths = adminRoots
        .map((root) => path.join(root, 'i18n', `${locale}.csv`))
        .filter((localePath) => fs.existsSync(localePath));
    const keys = new Set();

    localePaths.forEach((localePath) => {
        const rows = fs.readFileSync(localePath, 'utf8').trim().split('\n').filter(Boolean);
        const fileKeys = rows.map((row, index) => {
            const match = row.match(/^"((?:""|[^"])*)",/);

            assert.ok(match, `${localePath}:${index + 1} must be a quoted two-column CSV row`);

            return match[1].replace(/""/g, '"');
        });

        assert.equal(new Set(fileKeys).size, fileKeys.length, `${localePath} must not contain duplicate keys`);
        fileKeys.forEach((key) => keys.add(key));
    });

    return keys;
}

function decodeXmlText(value) {
    const entities = {amp: '&', lt: '<', gt: '>', quot: '"', apos: "'"};
    return value.replace(/&(amp|lt|gt|quot|apos);/g, (match, name) => entities[name]);
}

function phrases() {
    const result = new Set();
    const callPatterns = [
        /__\(\s*(['"])(.*?)\1/g,
        /\$t\(\s*(['"])(.*?)\1/g
    ];
    const markupPatterns = [
        /<(?:button_label|comment|label|message|title|tooltip)[^>]*>([^<]+)<\//g,
        /<comment[^>]*><!\[CDATA\[([\s\S]*?)\]\]><\/comment>/g,
        /\btitle="([^"]+)"[^>]*\btranslate="title"/g
    ];

    adminRoots.flatMap(filesUnder).forEach((file) => {
        const source = fs.readFileSync(file, 'utf8');

        callPatterns.forEach((pattern) => {
            let match;

            while ((match = pattern.exec(source))) {
                if (!/^\s*\./.test(source.slice(pattern.lastIndex))) {
                    result.add(match[2]);
                }
            }
        });
        markupPatterns.forEach((pattern) => {
            let match;

            while ((match = pattern.exec(source))) {
                result.add(decodeXmlText(match[1].trim()));
            }
        });
    });

    return result;
}

function isPolish(phrase) {
    return /[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]|^(?:Akcja|Aktyw|Anuluj|Atrybut|Auto(?:-|matycz)|Brak|Dodaj|Dodano|Dopas|Dostęp|Drzewo|Dziedz|Element|Filtruj|Gotowy|Integracja|Język|Języki|Kategoria|Kod|Kompletne|Ładowanie|Mapow|Masz|Najpierw|Nazwa|Nie|Niez|Now|Oczekuje|Odznacz|Odśwież|Opcja|Opcje|Operacja|Ostatnie |Parametry|Pob|Pokaz|Pokaż|Połącz|Ponawiam|Poprawne|Preview|Przeciągnij|Przejdź|Przełącz|Przetworzono|Rozwiń|Sekcje|Sort|Spróbuj|Store View aktywny|Synchronizować|Synchronizuj|Synchronizuję|Szukaj|Ta |Tego|Ten|To |Tworzenie|Upuść|Uruchamiam|Ustawienia|Usuń|Utwórz|Uzupełnij|Wczytaj|Włącz|Workspace wymaga|Wróć|Wszystkie|Wybierz|Wyczyść|Wyklucz|Wyłącz|Wymaga|Wymagany|Wyszukaj|Zachowane |Zamknij|Zapis|Zaznacz|Zgodne|Zmapowano|Zmień|Zwiń|brakuje|język|kategorii|mapowań|po zapisie|poprawnych|wybranych|z Ergonode|z Magento)/.test(phrase);
}

test('language detection recognizes Polish phrases without treating English automatic as Polish', () => {
    assert.equal(isPolish('Automatic mapping'), false);
    assert.equal(isPolish('Automatic updates'), false);
    assert.equal(isPolish('Auto-mapuj'), true);
    assert.equal(isPolish('Automatycznie dopasuj'), true);
    assert.equal(isPolish('Ostatnie poprawne pobranie (UTC): %1'), true);
    assert.equal(isPolish('Ostatnie sprawdzenie (UTC): %1'), true);
    assert.equal(isPolish('Zachowane kategorie (%1)'), true);
});

test('XML phrases use decoded translation keys', () => {
    assert.equal(decodeXmlText('Name &gt; In a field'), 'Name > In a field');
    assert.equal(decodeXmlText('A &amp; B'), 'A & B');
});

test('admin phrases are covered for Polish and English administrator locales', () => {
    const enUs = csvKeys('en_US');
    const plPl = csvKeys('pl_PL');
    const untranslatedTechnicalLabels = new Set([
        'Ergonode',
        'Ergonode tree_code',
        'Ergonode tree_name',
        'Magento 2'
    ]);
    const missingEnglish = [];
    const missingPolish = [];

    phrases().forEach((phrase) => {
        if (isPolish(phrase)) {
            if (!enUs.has(phrase)) {
                missingEnglish.push(phrase);
            }
        } else if (/[A-Za-z]/.test(phrase) && !untranslatedTechnicalLabels.has(phrase) && !plPl.has(phrase)) {
            missingPolish.push(phrase);
        }
    });

    assert.deepEqual(missingEnglish.sort(), []);
    assert.deepEqual(missingPolish.sort(), []);
});

test('JavaScript that creates administrator-facing messages uses Magento translation', () => {
    [
        'CoreAdminUi/view/adminhtml/web/js/attribute-mapping.js',
        'CoreAdminUi/view/adminhtml/web/js/option-mapping.js',
        'AttributePublisherAdminUi/view/adminhtml/web/js/ergonode-attribute-publisher-mapping.js',
        'CoreAdminUi/view/adminhtml/web/js/buttons.js',
        'CoreAdminUi/view/adminhtml/web/js/entity-options.js',
        'CoreAdminUi/view/adminhtml/web/js/mapping-elements.js',
        'CoreAdminUi/view/adminhtml/web/js/request.js',
        'CoreAdminUi/view/adminhtml/web/js/snapshot-removal.js',
        'CoreAdminUi/view/adminhtml/web/js/workspace.js',
        'LanguageAdminUi/view/adminhtml/web/js/language-mapping.js'
    ].forEach((relativePath) => {
        const [moduleName, ...segments] = relativePath.split('/');
        assert.match(fs.readFileSync(path.join(modulePath(moduleName), ...segments), 'utf8'), /mage\/translate/);
    });
});
