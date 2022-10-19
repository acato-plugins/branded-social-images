/*=========================================
 GULP
 =========================================*/
var $ = require('gulp-load-plugins')({
		rename: {'gulp': 'g'},
		pattern: ['gulp', 'gulp-*', 'gulp.*', '@*/gulp{-,.}*']
	}),
	sass = require('gulp-sass')(require('sass')),
	webpack = require('webpack'),
	webpackStream = require('webpack-stream'),
	webpackConfig = require('./webpack.config.js'),
	errorHandler = function (err) {
      console.log(err.message + ' on line ' + err.lineNumber + ' in file : ' + err.fileName);
    };

// Browsers to target when prefixing CSS.
var BROWSERS = ['last 2 versions', 'ie >= 9'];

$.g.task('styles', function () {
	var r,s = [
		{source: ['src/*.scss', '!src/_*.scss'], targetDir: 'admin', title: 'Style'}
	];
	for (var i in s) {
		r = $.g.src(
			s[i].source)
			.pipe(sass()).on('error', errorHandler)
			.pipe($.autoprefixer({browsers: BROWSERS}))
			.pipe($.g.dest(s[i].targetDir + '/'))
			.pipe($.cssnano()).pipe($.rename({suffix: '.min'}))
			.pipe($.g.dest(s[i].targetDir + '/'))
			.pipe($.livereload())
			.pipe($.notify(s[i].title + ' compiled'));
	}
	return r;
});

$.g.task('scripts', function () {
	var r,s = [
		{source: 'src/admin.js', targetDir: 'admin', title: 'Scripts'}
	];

	for (var i in s) {
		r = $.g.src(
			s[i].source)
			.pipe($.jshint())
			.pipe($.jshint.reporter('jshint-stylish'))
			.pipe(webpackStream(webpackConfig), webpack).on('error', errorHandler)
			.pipe($.g.dest(s[i].targetDir + '/'))
			.pipe($.uglify()).pipe($.rename({suffix: '.min'}))
			.pipe($.g.dest(s[i].targetDir + '/'))
			.pipe($.livereload())
			.pipe($.notify(s[i].title + ' compiled'));
	}
	return r;
});

/*-----------------------------------------
 WATCH, DEFAULT
 -----------------------------------------*/
$.g.task('watch', function () {
	$.livereload.listen();

	$.g.watch('src/**/*.scss', $.g.series('styles'));
	$.g.watch('src/**/*.js', $.g.series('scripts'));
});

$.g.task('default', $.g.series('styles', 'scripts', 'watch'));
