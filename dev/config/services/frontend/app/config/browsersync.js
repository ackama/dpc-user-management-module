module.exports = {
    open: false,
    proxy:  {
        target:"http://wordpress"
    },
    files: [
        'public/wp-content/themes/sqw/assets/dist/js/*',
        'public/wp-content/themes/sqw/assets/dist/css/*',
        'public/wp-content/themes/sqw/templates/**/*'
    ]
};
