var concat = require('concat');
var packageJSON = require('../package.json');
concat(packageJSON.concat.files, packageJSON.concat.dist).then(function (result) {
    console.log(result);
}).catch(function (error) {
    console.log(error);
    process.exit();
});
