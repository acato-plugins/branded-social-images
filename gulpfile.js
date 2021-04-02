/*=========================================
 GULP
 =========================================*/
var $ = require('gulp-load-plugins')({
    rename: {'gulp': 'g'},
    pattern: ['gulp', 'gulp-*', 'gulp.*', '@*/gulp{-,.}*']
});

var swallowError = function (error) {
    console.log(error.toString());
    this.emit('end');
};

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
//
// $.g.task('scripts', function () {
//     var s = [
//         {source: 'assets/kenteken.js', targetDir: 'public', targetName: 'kenteken.js', title: 'Script'}
//     ];
//     for (var i in s) {
//         $.g.src(
//             s[i].source)
//             .pipe($.concat(s[i].targetName))
//             .pipe($.uglify())
//             .pipe($.g.dest(s[i].targetDir + '/'))
//             .pipe($.livereload())
//             .pipe($.notify(s[i].title + ' compiled'));
//     }
// });

/*-----------------------------------------
 WATCH, DEFAULT
 -----------------------------------------*/
$.g.task('watch', function () {
    $.livereload.listen();

    $.g.watch('css/**/*.scss', ['styles']);
    // $.g.watch('assets/**/*.js', ['scripts']);

});

$.g.task('default', ['styles', /*'scripts',*/ 'watch']);
