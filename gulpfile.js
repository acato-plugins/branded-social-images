/*=========================================
 GULP
 =========================================*/
var $ = require('gulp-load-plugins')({
    rename: {'gulp': 'g'},
    pattern: ['gulp', 'gulp-*', 'gulp.*', '@*/gulp{-,.}*']
});

// Browsers to target when prefixing CSS.
var BROWSERS = ['last 2 versions', 'ie >= 9'];

/*-----------------------------------------
 STYLES
 -----------------------------------------*/
$.g.task('styles', function () {
    var s = [
        {source: 'css/*.scss', targetDir: 'css', title: 'Style'}
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

/*-----------------------------------------
 WATCH, DEFAULT
 -----------------------------------------*/
$.g.task('watch', function () {
    $.livereload.listen();

    $.g.watch('css/**/*.scss', ['styles']);
});

$.g.task('default', ['styles', 'watch']);
