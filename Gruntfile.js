module.exports = function(grunt) {

    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        sprite: {
            dark_icons: {
                src: 'src/bb-themes/admin_default/images/icons/dark/*.png',
                dest: 'src/bb-themes/admin_default/sprites/dark-icons-sprite.png',
                destCss: 'src/bb-themes/admin_default/css/dark-icons-sprite.css',
                cssOpts: {
                    cssSelector: function (item) {
                        return '.sprite-' + item.name;
                    }
                }
            },
            topnav: {
                src: 'src/bb-themes/admin_default/images/icons/topnav/*.png',
                dest: 'src/bb-themes/admin_default/sprites/topnav-sprite.png',
                destCss: 'src/bb-themes/admin_default/css/topnav-sprite.css',
                cssOpts: {
                    cssSelector: function (item) {
                        return '.sprite-topnav-' + item.name;
                    }
                }
            },
            middleNav: {
                src: 'src/bb-themes/admin_default/images/icons/middlenav/used/*.png',
                dest: 'src/bb-themes/admin_default/sprites/dark-icons-23-sprite.png',
                destCss: 'src/bb-themes/admin_default/css/dark-icons-23-sprite.css',
                cssOpts: {
                    cssSelector: function (item) {
                        return '.sprite-23-' + item.name;
                    }
                }
            }
        },
    });

    // Load packages
    grunt.loadNpmTasks('grunt-spritesmith');

    // Default task(s).
    grunt.registerTask('default', ['sprite']);

};