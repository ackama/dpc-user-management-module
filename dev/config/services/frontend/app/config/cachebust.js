const jsonfile = require('jsonfile');
const argv = require('minimist')(process.argv.splice(2));
const rename = require('rename');
const shortid = require('shortid');
const manifest = argv.m;
const fileArray = argv._;
const fs = require('fs');

function copyFile(source, target) {
    fs.writeFileSync(target, fs.readFileSync(source), (err) => {
        if (err) {
            process.exit(err);
        }
    });
}

module.exports = function () {
    const id = shortid.generate();
    let obj = {};
    fileArray.forEach((file) => {
        let filename = file.substring(file.lastIndexOf("/")+1, file.length);
        let filenameWithPath = file;
        let filenameNew = rename(filename, {suffix: `-${id}`});
        let filenameNewWithPath = rename(filenameWithPath, {suffix: `-${id}`});
        obj[filename] = filenameNew;
        copyFile(file,filenameNewWithPath,() => {});
    });

    return jsonfile.writeFile(manifest, obj, function (err) {
        if (err) {
            process.exit(err);
        } else {
            console.log("Files cachebusted.");
        }
    });
}();
