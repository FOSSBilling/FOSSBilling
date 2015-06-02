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

        clean: {
            css: {
                src: [
                    "src/bb-themes/admin_default/css/min-temp.css"
                ]
            },
            js: {
                src: [
                    "src/bb-themes/admin_default/js/boxbilling.js"
                ]
            }
        },

        concat_css: {
            style: {
                src: [
                    'src/bb-themes/admin_default/css/dark-icons-sprite.css',
                    'src/bb-themes/admin_default/css/dark-icons-23-sprite.css',
                    'src/bb-themes/admin_default/css/topnav-sprite.css',
                    'src/bb-themes/admin_default/css/jquery-ui.css',
                    'src/bb-themes/admin_default/css/bb.css',
                    'src/bb-themes/admin_default/css/reset.css',
                    'src/bb-themes/admin_default/css/dataTable.css',
                    'src/bb-themes/admin_default/css/ui_custom.css',
                    'src/bb-themes/admin_default/css/icons.css.css',
                    'src/bb-themes/admin_default/css/main.css'
                ],
                dest: "src/bb-themes/admin_default/css/min-temp.css"
            }
        },

        cssmin: {
            target: {
                files: [
                    {
                        'src/bb-themes/admin_default/css/min.css': ['src/bb-themes/admin_default/css/min-temp.css']
                    }
                ]
            }
        },

        concat: {
            default: {
                src: [
                    'src/bb-themes/admin_default/js/jquery.min.js',
                    'src/bb-themes/admin_default/js/ui/jquery.alerts.js',
                    'src/bb-themes/admin_default/js/ui/jquery.tipsy.js',
                    'src/bb-themes/admin_default/js/jquery.collapsible.min.js',
                    'src/bb-themes/admin_default/js/forms/forms.js',
                    'src/bb-themes/admin_default/js/jquery.ToTop.js',
                    'src/bb-themes/admin_default/js/jquery.scrollTo-min.js',
                    'src/bb-themes/admin_default/js/jquery-ui.js',
                ],
                dest: 'src/bb-themes/admin_default/js/boxbilling.js'
            },
        },

        uglify: {
            js: {
                files: {
                    'src/bb-themes/admin_default/js/boxbilling.min.js': ['src/bb-themes/admin_default/js/boxbilling.js'],
                }
            }
        },
    });

    // Load packages
    grunt.loadNpmTasks('grunt-spritesmith');

    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-concat-css');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-uglify');

    // Grunt task(s).
    grunt.registerTask('default', ['create-sprites', 'css', 'js']);

    grunt.registerTask('create-sprites', ['sprite']);
    grunt.registerTask('css', ['concat_css', 'cssmin', 'clean:css']);
    grunt.registerTask('js', ['concat:default','uglify', 'clean:js']);



};