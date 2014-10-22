'use strict';

module.exports = function(grunt) {

	// Project configuration.
	grunt.initConfig({

		// Load grunt project configuration
		pkg: grunt.file.readJSON('package.json'),

		// Configure less CSS compiler
		less: {
			build: {
				options: {
					compress: true,
					cleancss: true,
					ieCompat: true
				},
				files: {
					'assets/css/aqm.css': [
						'assets/src/less/aqm.less'
					]
				}
			}
		},

		// Configure JSHint
		jshint: {
			test: {
				src: 'assets/src/js/*.js'
			}
		},

		// Concatenate scripts
		concat: {
			build: {
				files: {
					'assets/js/aqm.js': [
						'assets/src/js/aqm.js',
						'assets/src/js/aqm-panel.js'
					]
				}
			}
		},

		// Minimize scripts
		uglify: {
			options: {
				banner: '/*! <%= pkg.name %> <%= grunt.template.today("yyyy-mm-dd") %> */\n'
			},
			build: {
				files: {
					'assets/js/aqm.min.js' : 'assets/js/aqm.js',
				}
			}
		},

		// Watch for changes on some files and auto-compile them
		watch: {
			less: {
				files: ['assets/src/less/*.less'],
				tasks: ['less']
			},
			js: {
				files: ['assets/src/js/*.js'],
				tasks: ['jshint', 'concat', 'uglify']
			}
		}

	});

	// Load tasks
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-less');
	grunt.loadNpmTasks('grunt-contrib-nodeunit');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-watch');

	// Default task(s).
	grunt.registerTask('default', ['watch']);

};
