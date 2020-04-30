const rimraf = require("rimraf");
function clean() {
    let paths = [
        "logs",
        "public/wp-content/themes/sqw/assets/dist",
        "public/wp-content/themes/sqw/templates/partials/svg",
        "public/wp-content/plugins/index.php",
        "public/wp-content/plugins/hello.php",
        "public/wp-content/plugins/akismet",
        "public/wp-content/themes/twentysixteen",
        "public/wp-content/themes/twentyfifteen",
        "public/wp-content/themes/twentyseventeen",
        "public/wp-content/themes/twentynineteen",
        "public/wp-content/themes/index.php",
        "public/wp-content/index.php",
        "public/wp-content/upgrade"
    ];
    return paths.map(function (path) {
        console.log("Removing: ", path);
        return rimraf(path, {}, function (error) {
            if (error) {
                process.exit();
            }
        });
    });
}

module.exports = clean();
