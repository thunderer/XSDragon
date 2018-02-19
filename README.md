# XSDragon

XSDragon is a solution for generating PHP classes from XSD schemas, parsing XML into PHP objects, and serializing PHP objects to XML. Generated classes are immutable and fully leverage capabilities of PHP7 by hinting all types and enforcing XSD constraints. This means that well-designed XSD schema results in equally well-designed PHP code which is guarded against accidental errors and technically invalid state.

# Usage

To generate PHP classes from XSD files you need to create analyzer instance, use it to generate runtime representation of the schemas, create generator instance and use it to generate the classes from schemas object. The code below demostrates sample script that can be used to create command-line utility for that:

```php
$xsdPath = '/path/to/directory/with/xsd/schemas';
$phpPath = '/path/to/directory/for/generated/php/classes';

// NullLogger will discard all runtime information (use EchoLogger to display it)
$logger = new NullLogger();
// analyzer takes logger as dependency
$analyzer = new XsdAnalyzer($logger);
// analyzer generates schemas representation from files in specified directory
$schemas = $analyzer->createFromDirectories([$xsdPath]);

// counter contains generation process summary
$counter = new Counter();
// filesystem writes class files to disk
$fs = new FilePutContentsFilesystem($phpPath);
// namespace resolver translates XML namespaces to filesystem paths
$ns = new CustomNamespaceResolver();
// generator requires above dependencies
$generator = new PrimitivePhpGenerator($fs, $ns, $logger, $counter);
// generator processes schemas representation and generates classes
$generator->generate($schemas);

// display library logo and process summary
echo XsdUtility::logo();
echo XsdUtility::formatCounter($counter);
```

# Notes

Disabling XDebug greatly improves library performance. Examples from manual tests show more than 10x improvement on analysis and class generation of some XSD schema packages (PHP 7.2.2, i7 2600k, 16GiB DDR3, SSD):
* ~30 schemas, ~300 classes, ~2.2s down to ~0.3s (7.3x improvement),
* ~200 schemas, ~7200 classes, ~34.5s down to ~3.2s (10.8x improvement),
* ~440 schemas, ~36500 classes, ~22.0s down to ~4.4s (5.0x improvement).
