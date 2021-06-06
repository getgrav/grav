ChangeLog
=========

2.2.3 (2020-10-03)
------------------
* #191: add changelog and version bump that was missed in 2.2.2

2.2.2 (2020-10-03)
------------------
* #190: adjust libxml_disable_entity_loader calls ready for PHP 8.0 (@phil-davis)

2.2.1 (2020-05-11)
------------------

* #183: fixed warning 'xml cannot be empty while reading', which might lead to a infinite-loop (@mrow4a)
* #179, #178, #177 #176: several build/continous integration related improvements (@phil-davis)

2.2.0 (2020-01-31)
------------------

* #171: Added Support for PHP 7.4, dropped Support for PHP 7.0 (@staabm, @phil-davis)
* #174: Update testsuite to phpunit8 (@phil-davis)
* Added phpstan coverage (@phil-davis)
* #144: Added a new `functionCaller` deserializer function for executing a callable when reading a XML
element (@vsouz4)


2.1.3 (2019-08-14)
------------------

* #166: Throw exception when empty inputs found


2.1.2 (2019-01-09)
------------------

* #161: Prevent infinite loop on empty xml elements


2.1.1 (2018-10-09)
------------------

* #149: Properly detect xml parse errors in `parseCurrentElement()` edge-cases


2.1.0 (2018-02-08)
------------------

* #112: Added a `mixedContent` deserializer function, which might be useful
  if you're parsing HTML-like documents with elements that contain both text
  and other elements as siblings. (@staabm).


2.0.0 (2016-11-15)
------------------

* Now requires PHP 7.
* Uses typehints everywhere.
* Fixed some minor strict typing-related issues.
* Removed workaround for PHP bug [64230](https://bugs.php.net/bug.php?id=64230).


1.5.0 (2016-10-09)
------------------

* Now requires PHP 5.5.
* Using `finally` to always roll back the context stack when serializing.
* #94: Fixed an infinite loop condition when reading some invalid XML
  documents.


1.4.2 (2016-05-19)
------------------

* The `contextStack` in the Reader object is now correctly rolled back in
  error conditions (@staabm).
* repeatingElements deserializer now still parses if a bare element name
  without clark notation was given.
* `$elementMap` in the Reader now also supports bare element names.
* `Service::expect()` can now also work with bare element names.


1.4.1 (2016-03-12)
-----------------

* Parsing clark-notation is now cached. This can speed up parsing large
  documents with lots of repeating elements a fair bit. (@icewind1991).


1.4.0 (2016-02-14)
------------------

* Any array thrown into the serializer with numeric keys is now simply
  traversed and each individual item is serialized. This fixes an issue
  related to serializing value objects with array children.
* When serializing value objects, properties that have a null value or an
  empty array are now skipped. We believe this to be the saner default, but
  does constitute a BC break for those depending on this.
* Serializing array properties in value objects was broken.


1.3.0 (2015-12-29)
------------------

* The `Service` class adds a new `mapValueObject` method which provides basic
  capabilities to map between ValueObjects and XML.
* #61: You can now specify serializers for specific classes, allowing you
  separate the object you want to serialize from the serializer. This uses the
  `$classMap` property which is defined on both the `Service` and `Writer`.
* It's now possible to pass an array of possible root elements to
  `Sabre\Xml\Service::expect()`.
* Moved some parsing logic to `Reader::getDeserializerForElementName()`,
  so people with more advanced use-cases can implement their own logic there.
* #63: When serializing elements using arrays, the `value` key in the array is
  now optional.
* #62: Added a `keyValue` deserializer function. This can be used instead of
  the `Element\KeyValue` class and is a lot more flexible. (@staabm)
* Also added an `enum` deserializer function to replace
  `Element\Elements`.
* Using an empty string for a namespace prefix now has the same effect as
  `null`.


1.2.0 (2015-08-30)
------------------

* #53: Added `parseGetElements`, a function like `parseInnerTree`, except
  that it always returns an array of elements, or an empty array.


1.1.0 (2015-06-29)
------------------

* #44, #45: Catching broken and invalid XML better and throwing
  `Sabre\Xml\LibXMLException` whenever we encounter errors. (@stefanmajoor,
   @DaanBiesterbos)


1.0.0 (2015-05-25)
------------------

* No functional changes since 0.4.3. Marking it as 1.0.0 as a promise for
  API stability.
* Using php-cs-fixer for automated CS enforcement.


0.4.3 (2015-04-01)
-----------------

* Minor tweaks for the public release.


0.4.2 (2015-03-20)
------------------

* Removed `constants.php` again. They messed with PHPUnit and don't really
  provide a great benefit.
* #41: Correctly handle self-closing xml elements.


0.4.1 (2015-03-19)
------------------

* #40: An element with an empty namespace (xmlns="") is not allowed to have a
  prefix. This is now fixed.


0.4.0 (2015-03-18)
------------------

* Added `Sabre\Xml\Service`. This is intended as a simple way to centrally
  configure xml applications and easily parse/write things from there. #35, #38.
* Renamed 'baseUri' to 'contextUri' everywhere.
* #36: Added a few convenience constants to `lib/constants.php`.
* `Sabre\Xml\Util::parseClarkNotation` is now in the `Sabre\Xml\Service` class.


0.3.1 (2015-02-08)
------------------

* Added `XmlDeserializable` to match `XmlSerializable`.


0.3.0 (2015-02-06)
------------------

* Added `$elementMap` argument to parseInnerTree, for quickly overriding
  parsing rules within an element.


0.2.2 (2015-02-05)
------------------

* Now depends on sabre/uri 1.0.


0.2.1 (2014-12-17)
------------------

* LibXMLException now inherits from ParseException, so it's easy for users to
  catch any exception thrown by the parser.


0.2.0 (2014-12-05)
------------------

* Major BC Break: method names for the Element interface have been renamed
  from `serializeXml` and `deserializeXml` to `xmlSerialize` and
  `xmlDeserialize`. This is so that it matches PHP's `JsonSerializable`
  interface.
* #25: Added `XmlSerializable` to allow people to write serializers without
  having to implement a deserializer in the same class.
* #26: Renamed the `Sabre\XML` namespace to `Sabre\Xml`. Due to composer magic
  and the fact that PHP namespace are case-insensitive, this should not affect
  anyone, unless you are doing exact string matches on class names.
* #23: It's not possible to automatically extract or serialize Xml fragments
  from documents using `Sabre\Xml\Element\XmlFragment`.


0.1.0 (2014-11-24)
------------------

* #16: Added ability to override `elementMap`, `namespaceMap` and `baseUri` for
  a fragment of a document during reading an writing using `pushContext` and
  `popContext`.
* Removed: `Writer::$context` and `Reader::$context`.
* #15: Added `Reader::$baseUri` to match `Writer::$baseUri`.
* #20: Allow callbacks to be used instead of `Element` classes in the `Reader`.
* #25: Added `readText` to quickly grab all text from a node and advance the
  reader to the next node.
* #15: Added `Sabre\XML\Element\Uri`.


0.0.6 (2014-09-26)
------------------

* Added: `CData` element.
* #13: Better support for xml with no namespaces. (@kalmas)
* Switched to PSR-4 directory structure.


0.0.5 (2013-03-27)
------------------

* Added: baseUri property to the Writer class.
* Added: The writeElement method can now write complex elements.
* Added: Throwing exception when invalid objects are written.


0.0.4 (2013-03-14)
------------------

* Fixed: The KeyValue parser was skipping over elements when there was no
  whitespace between them.
* Fixed: Clearing libxml errors after parsing.
* Added: Support for CDATA.
* Added: Context properties.


0.0.3 (2013-02-22)
------------------

* Changed: Reader::parse returns an array with 1 level less depth.
* Added: A LibXMLException is now thrown if the XMLReader comes across an error.
* Fixed: Both the Elements and KeyValue parsers had severe issues with
  nesting.
* Fixed: The reader now detects when the end of the document is hit before it
  should (because we're still parsing an element).


0.0.2 (2013-02-17)
------------------

* Added: Elements parser.
* Added: KeyValue parser.
* Change: Reader::parseSubTree is now named parseInnerTree, and returns either
  a string (in case of a text-node), or an array (in case there were child
  elements).
* Added: Reader::parseCurrentElement is now public.


0.0.1 (2013-02-07)
------------------

* First alpha release

Project started: 2012-11-13. First experiments in June 2009.
