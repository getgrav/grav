/* WE USE
    - sass for scss compilation
    - rollup-stream for js bundling
    - terser for js minification
*/
import gulp from 'gulp';
import sass from 'gulp-sass';
import cleancss from 'gulp-clean-css';
import csscomb from 'gulp-csscomb';
import rename from 'gulp-rename';
import autoprefixer from 'gulp-autoprefixer';

import rollupStream from '@rollup/stream';
import buffer from 'vinyl-buffer';
import sourcemaps from 'gulp-sourcemaps';
import source from 'vinyl-source-stream';
import { nodeResolve } from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';
import css from 'rollup-plugin-css-porter';

import terser from 'gulp-terser';

import { parallel, series } from 'gulp';

// configure the paths
let scss_watch_dir = ['./scss/**/*.scss'];
let scss_src_dir = './scss/*.scss';
let css_dest_dir = './dist/css';

// all javascript using modules must be in the `js-es6` folder
let js_watch_dir = ['./js-es6/**/*.js'];
// let js_files = []

let rollup = js_file => {
    const options = {
        input: `./js-es6/${js_file}.js`,
        plugins: [
            css({ dest: `./dist/css/${js_file}.css`, raw: false }),
            commonjs(),
            nodeResolve()
        ],
        output: {
            format: 'iife',
            sourcemap: true
        }
    };
    return rollupStream(options)
        .pipe(source(`${js_file}.js`))
        .pipe(buffer())
        .pipe(sourcemaps.init({ loadMaps: true }))
        .pipe(terser())
        .pipe(sourcemaps.write('./'))
        .pipe(gulp.dest('dist/js'));
};

let scss = () =>
    gulp.src(scss_src_dir)
        .pipe(sourcemaps.init())
        .pipe(sass({
            outputStyle: 'compact',
            precision: 10
        }).on('error', sass.logError)
        )
        .pipe(sourcemaps.write())
        .pipe(autoprefixer())
        .pipe(gulp.dest(css_dest_dir))
        .pipe(csscomb())
        .pipe(cleancss())
        .pipe(rename({
            suffix: '.min'
        }))
        .pipe(gulp.dest(css_dest_dir));

let watch_scss = () =>
    gulp.watch(scss_watch_dir, scss);

let build = scss;

exports.watch_css = watch_scss;
exports.watch = watch_scss;
exports.build = build;
exports.default = build;
