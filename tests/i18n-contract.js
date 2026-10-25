'use strict';

var fs = require('fs');
var path = require('path');
var root = path.resolve(__dirname, '..');
var sourceStrings = new Set();

function walk(directory) {
	fs.readdirSync(directory, { withFileTypes: true }).forEach(function (entry) {
		var fullPath = path.join(directory, entry.name);
		var relative = path.relative(root, fullPath).replace(/\\/g, '/');

		if (entry.isDirectory()) {
			if (['.git', 'dist', 'tests', 'bepusdt-woocommerce'].indexOf(relative.split('/')[0]) === -1) {
				walk(fullPath);
			}
			return;
		}

		if (!/\.(php|js)$/.test(entry.name)) {
			return;
		}

		var source = fs.readFileSync(fullPath, 'utf8');
		var expression = /(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'((?:\\.|[^'\\])*)'\s*,\s*'bepusdt-woocommerce'/g;
		var match;
		while ((match = expression.exec(source))) {
			sourceStrings.add(match[1].replace(/\\'/g, "'").replace(/\\\\/g, '\\'));
		}
	});
}

walk(root);

var pot = fs.readFileSync(path.join(root, 'languages', 'bepusdt-woocommerce.pot'), 'utf8');
var potStrings = new Set();
pot.split(/\r?\n/).forEach(function (line) {
	var match = line.match(/^msgid "(.*)"$/);
	if (match && match[1]) {
		potStrings.add(JSON.parse('"' + match[1].replace(/"/g, '\\"') + '"'));
	}
});

var missing = Array.from(sourceStrings).filter(function (value) { return !potStrings.has(value); });
if (missing.length) {
	throw new Error('Missing POT strings:\n' + missing.join('\n'));
}

process.stdout.write('Translation catalog contract checks passed (' + sourceStrings.size + ' source strings).\n');
