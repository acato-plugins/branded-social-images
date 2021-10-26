/*=========================================
 GULP
 =========================================*/
var $ = require('gulp-load-plugins')({
    rename: {'gulp': 'g'},
    pattern: ['gulp', 'gulp-*', 'gulp.*', '@*/gulp{-,.}*']
});
var webpack = require('webpack');
var webpackStream = require('webpack-stream');
var webpackConfig = require('./webpack.config.js');

// Browsers to target when prefixing CSS.
var BROWSERS = ['last 2 versions', 'ie >= 9'];

$.g.task('styles', function () {
    var s = [
        {source: 'src/*.scss', targetDir: 'admin', title: 'Style'}
    ];
    for (var i in s) {
        $.g.src(
            s[i].source)
            .pipe($.sass()).on('error', $.sass.logError)
            .pipe($.autoprefixer({browsers: BROWSERS}))
            .pipe($.g.dest(s[i].targetDir + '/'))
            .pipe($.livereload())
            .pipe($.notify(s[i].title + ' compiled'));
    }
});

$.g.task('scripts', function () {
	var s = [
		{source: 'src/admin.js', targetDir: 'admin', title: 'Scripts'}
	];

	for (var i in s) {
		$.g.src(
			s[i].source)
			.pipe(webpackStream(webpackConfig), webpack).on('error', $.sass.logError)
			.pipe($.g.dest(s[i].targetDir + '/'))
			.pipe($.livereload())
			.pipe($.notify(s[i].title + ' compiled'));
	}
});

/*-----------------------------------------
 WATCH, DEFAULT
 -----------------------------------------*/
$.g.task('watch', function () {
    $.livereload.listen();

    $.g.watch('src/**/*.scss', ['styles']);
    $.g.watch('src/**/*.js', ['scripts']);
});

$.g.task('default', ['styles', 'scripts', 'watch']);
