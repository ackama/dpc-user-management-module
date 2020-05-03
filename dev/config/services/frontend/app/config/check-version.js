var semver = require('semver');
var packageJson = require('../package.json');
var colors = require('colors/safe');
var version = packageJson.engines.node;

if (!semver.satisfies(process.version, version)) {
    console.log(colors.red.underline('Required node version' + version + ' not satisfied with current version ' + process.version));
    process.exit(1);
}