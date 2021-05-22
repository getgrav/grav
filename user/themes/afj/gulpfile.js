var gulp = require('gulp');
var sass = require('gulp-sass');
var cleancss = require('gulp-clean-css');
var csscomb = require('gulp-csscomb');
var rename = require('gulp-rename');
var autoprefixer = require('gulp-autoprefixer');
var sourcemaps = require('gulp-sourcemaps');

// configure the paths
var watch_dir = './scss/**/*.scss';
var src_dir = './scss/*.scss';
var dest_dir = './css-compiled';

var paths = {
    source: src_dir
};

function watch() {
  return gulp.watch(watch_dir, build);
}

function build() {
  return gulp.src(paths.source)
      .pipe(sourcemaps.init())
      .pipe(sass({
            outputStyle: 'compact',
            precision: 10
          }).on('error', sass.logError)
      )
      .pipe(sourcemaps.write())
      .pipe(autoprefixer())
      .pipe(gulp.dest(dest_dir))
      .pipe(csscomb())
      .pipe(cleancss())
      .pipe(rename({
        suffix: '.min'
      }))
      .pipe(gulp.dest(dest_dir));
}

exports.watch = watch;
exports.build = build;
exports.default = build;
