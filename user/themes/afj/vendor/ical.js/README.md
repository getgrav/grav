# jsical - Javascript parser for rfc5545

This is a library to parse the iCalendar format defined in
[rfc5545](http://tools.ietf.org/html/rfc5545), as well as similar formats like
vCard.

There are still some issues to be taken care of, but the library works for most
cases. If you would like to help out and would like to discuss any API changes,
please [contact me](mailto:mozilla@kewis.ch) or create an issue.

The initial goal was to use it as a replacement for libical in the [Mozilla
Calendar Project](http://www.mozilla.org/projects/calendar/), but the library
has been written with the web in mind. This library is now called ICAL.js and
enables you to do all sorts of cool experiments with calendar data and the web.
I am also aiming for a caldav.js when this is done. Most algorithms here were
taken from [libical](https://github.com/libical/libical). If you are bugfixing
this library, please check if the fix can be upstreamed to libical.

[![Build Status](https://secure.travis-ci.org/mozilla-comm/ical.js.png?branch=master)](http://travis-ci.org/mozilla-comm/ical.js) [![Coverage Status](https://coveralls.io/repos/mozilla-comm/ical.js/badge.svg)](https://coveralls.io/r/mozilla-comm/ical.js) [![npm version](https://badge.fury.io/js/ical.js.svg)](http://badge.fury.io/js/ical.js) [![CDNJS](https://img.shields.io/cdnjs/v/ical.js.svg)](https://cdnjs.com/libraries/ical.js)  
[![Greenkeeper badge](https://badges.greenkeeper.io/mozilla-comm/ical.js.svg)](https://greenkeeper.io/) [![Dependency Status](https://david-dm.org/mozilla-comm/ical.js.svg)](https://david-dm.org/mozilla-comm/ical.js) [![devDependency Status](https://david-dm.org/mozilla-comm/ical.js/dev-status.svg)](https://david-dm.org/mozilla-comm/ical.js?type=dev)

## Sandbox and Validator

If you want to try out ICAL.js right now, there is a
[jsfiddle](http://jsfiddle.net/kewisch/227efboL/) set up and ready to use. Read
on for documentation and example links.

There is also a validator that demonstrates how to use the library in a webpage
in the [sandbox/](https://github.com/mozilla-comm/ical.js/tree/master/sandbox)
subdirectory.

[Try the validator online](http://mozilla-comm.github.com/ical.js/validator.html), it always uses the latest copy of ICAL.js.

## Installing

You can install ICAL.js via [npm](https://www.npmjs.com/), if you would like to
use it in Node.js:
```
npm install ical.js
```

Alternatively, it is also available via [bower](http://bower.io/) for front-end
development:
```
bower install ical.js
```

ICAL.js has no dependencies and uses fairly basic JavaScript. Therefore, it
should work in all versions of Node.js and modern browsers. It does use getters
and setters, so the minimum version of Internet Explorer is 9.

## Documentation

For a few guides with code samples, please check out
[the wiki](https://github.com/mozilla-comm/ical.js/wiki). If you prefer,
full API documentation [is available here](http://mozilla-comm.github.io/ical.js/api/).
If you are missing anything, please don't hesitate to create an issue.

## Developing

To contribute to ICAL.js you need to set up the development environment. This
requires Node.js 8.x or later and grunt. Run the following steps to get
started.

Preferred way (to match building and packaging with official process):
```
yarn global add grunt-cli  # Might need to run with sudo
yarn --frozen-lockfile
```

Alternative way:
```
npm install -g grunt-cli  # Might need to run with sudo
npm install .
```

You can now dive into the code, run the tests and check coverage.

### Tests

Tests can either be run via Node.js or in the browser, but setting up the testing
infrastructure requires [node](https://github.com/nodejs/node). More
information on how to set up and run tests can be found on
[the wiki](https://github.com/mozilla-comm/ical.js/wiki/Running-Tests).

#### in Node.js

The quickest way to execute tests is using Node.js. Running the following command
will run all test suites: performance, acceptance and unit tests.

    grunt test-node

You can also select a single suite, or run a single test.

    grunt test-node:performance
    grunt test-node:acceptance
    grunt test-node:unit

    grunt test-node:single --test=test/parse_test.js

Appending the `--debug` option to any of the above commands will run the
test(s) with node-inspector. It will start the debugging server and open it in
Chrome or Opera, depending on what you have installed. The tests will pause
before execution starts so you can set breakpoints and debug the unit tests
you are working on.

If you run the performance tests comparison will be done between the current
working version (latest), a previous build of ICAL.js (previous) and the
unchanged copy of build/ical.js (from the master branch). See
[the wiki](https://github.com/mozilla-comm/ical.js/wiki/Running-Tests) for more
details.

#### in the browser

To run the browser tests, we are currently using [karma](http://karma-runner.github.io/).
To run tests with karma, you can run the following targets:

    grunt test-browser           # run all tests
    grunt karma:unit             # run only the unit tests
    grunt karma:acceptance       # run only the acceptance tests

Now you can visit [http://localhost:9876](http://localhost:9876) in your
browser. The test output will be shown in the console you started the grunt
task from. You can also run a single test:

    grunt karma:single --test=test/parse_test.js

The mentioned targets all run the tests from start to finish. If you would like
to debug the tests instead, you can add the `--debug` flag. Once you open the
browser there will be a "debug" button. Clicking on the button opens am empty
page, but if you open your browser's developer tools you will see the test
output. You can reload this page as often as you want until all tests are
running.

Last off, if you add the `--remote` option, karma will listen on all
interfaces. This is useful if you are running the browser to test in a VM, for
example when using [Internet Exporer VM images](https://developer.microsoft.com/en-us/microsoft-edge/tools/vms/).

### Code Coverage
ICAL.js is set up to calculate code coverage. You can
[view the coverage results](https://coveralls.io/r/mozilla-comm/ical.js)
online, or run them locally to make sure new code is covered. Running `grunt
coverage` will run the unit test suite measuring coverage. You can then open
`coverage/lcov-report/index.html` to view the results in your browser.

### Linters
To make sure all ICAL.js code uses a common style, please run the linters using
`grunt linters`. Please make sure you fix any issues shown by this command
before sending a pull request.

### Documentation
You can generate the documentation locally, this is also helpful to ensure the
jsdoc you have written is valid. To do so, run `grunt jsdoc`. You will find the
output in the `api/` subdirectory.

### Packaging
When you are done with your work, you can run `grunt package` to create the
single-file build for use in the browser, including its minified counterpart
and the source map.

## License
ical.js is licensed under the
[Mozilla Public License](https://www.mozilla.org/MPL/2.0/), version 2.0.
