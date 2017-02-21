<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Analyzer\XsdAnalyzer;
use Thunder\Xsdragon\Counter\Counter;
use Thunder\Xsdragon\Generator\PrimitivePhpGenerator;
use Thunder\Xsdragon\Logger\NullLogger;
use Thunder\Xsdragon\NamespaceResolver\ConstantNamespaceResolver;
use Thunder\Xsdragon\Filesystem\MemoryFilesystem;
use Thunder\Xsdragon\Parser\PrimitiveXmlParser;
use Thunder\Xsdragon\Serializer\PrimitiveStringXmlSerializer;
use Thunder\Xsdragon\Tests\Traits\XsdragonTestTrait;

final class PrimitivePhpGeneratorTest extends TestCase
{
    use XsdragonTestTrait;

    public function testNestedNamespacedElements()
    {
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings([
            $this->fixture('xsd/nestedNamespacedElement/root.xsd'),
            $this->fixture('xsd/nestedNamespacedElement/first.xsd'),
            $this->fixture('xsd/nestedNamespacedElement/second.xsd'),
            $this->fixture('xsd/nestedNamespacedElement/third.xsd'),
            $this->fixture('xsd/nestedNamespacedElement/fourth.xsd'),
            $this->fixture('xsd/nestedNamespacedElement/type.xsd'),
        ]);

        $filesystem = new MemoryFilesystem('dir');
        $resolver = new ConstantNamespaceResolver('PhpNs\\Ns5P');
        $generator = new PrimitivePhpGenerator($filesystem, $resolver, new NullLogger(), new Counter());
        $generator->generate($xsd);
        $files = $filesystem->getFiles();
        $schema = array_pop($files);

        $this->assertCount(7, $files);
        $this->assertContains('dir/schema.php', $schema);

        $this->assertContains('dir/PhpNs/Ns5P/Root.php', $files[0]);
        $this->assertContains('dir/PhpNs/Ns5P/RootType.php', $files[1]);
        $this->assertContains('dir/PhpNs/Ns5P/FirstType.php', $files[2]);
        $this->assertContains('dir/PhpNs/Ns5P/SecondType.php', $files[3]);
        $this->assertContains('dir/PhpNs/Ns5P/ThirdType.php', $files[4]);
        $this->assertContains('dir/PhpNs/Ns5P/FourthType.php', $files[5]);
        $this->assertContains('dir/PhpNs/Ns5P/StringType.php', $files[6]);
        $this->evalFiles($files);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<root xmlns="http://example.org/schema" 
    xmlns:firstNs="http://example.org/schema/firstNs"
    xmlns:secondNs="http://example.org/schema/secondNs"
    xmlns:thirdNs="http://example.org/schema/thirdNs"
    xmlns:fourthNs="http://example.org/schema/fourthNs"
    xmlns:type="http://example.org/schema/type">
    <first>
        <firstNs:second>
            <secondNs:third>
                <thirdNs:fourth>
                    <fourthNs:simple>value</fourthNs:simple>
                </thirdNs:fourth>
            </secondNs:third>
        </firstNs:second>
    </first>
</root>';

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $parser = new PrimitiveXmlParser($xsd, $resolver, new NullLogger());
        $serializer = new PrimitiveStringXmlSerializer($xsd, new NullLogger());
        $root = $parser->parse($xml);
        $this->assertXmlStringEqualsXmlString($xml, $serializer->serialize($root));

        $parser = new PrimitiveXmlParser($xsd, $resolver, new NullLogger());
        $root = $parser->parse($xml);

        $this->assertSame('value', $root->getFirst()->getSecond()->getThird()->getFourth()->getSimple());
    }

    public function testComplexTypeWithSequenceWithChoiceBetweenElementAndSequence()
    {
        $xsdString = $this->fixture('xsd/complexTypeWithSequenceWithChoiceBetweenElementAndSequence.xsd');
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings([$xsdString]);

        $writer = new MemoryFilesystem('dir');
        $resolver = new ConstantNamespaceResolver('PhpNs\\Ns1P');
        $generator = new PrimitivePhpGenerator($writer, $resolver, new NullLogger(), new Counter());
        $generator->generate($xsd);
        $files = $writer->getFiles();
        $schema = array_pop($files);

        $this->assertCount(6, $files);
        $this->assertContains('dir/schema.php', $schema);

        $this->assertContains('dir/PhpNs/Ns1P/ComplexTypeWithSequenceWithChoiceAndElement_Choice.php', $files[0]);
        $this->assertContains('final class ComplexTypeWithSequenceWithChoiceAndElement_Choice', $files[0]);
        $this->assertContains('public static function createFromElementWithinChoice(int $elementWithinChoice): self', $files[0]);
        $this->assertContains('public static function createFromComplexTypeWithSequenceWithChoiceAndElement_Sequence(string $seqEl0, int $seqEl1, string $seqEl2): self', $files[0]);

        $this->assertContains('dir/PhpNs/Ns1P/SeqWithChoice.php', $files[1]);
        $this->assertContains('final class SeqWithChoice', $files[1]);
        $this->assertContains('public function __construct(ComplexTypeWithSequenceWithChoiceAndElement_Choice $complexTypeWithSequenceWithChoiceAndElement_Choice, string $elementWithinSequence = null)', $files[1]);

        $this->assertContains('dir/PhpNs/Ns1P/ComplexTypeWithSequenceWithChoiceAndElement_Choice.php', $files[2]);
        $this->assertContains('final class ComplexTypeWithSequenceWithChoiceAndElement_Choice', $files[2]);
        $this->assertContains('public static function createFromElementWithinChoice(int $elementWithinChoice): self', $files[2]);
        $this->assertContains('public static function createFromComplexTypeWithSequenceWithChoiceAndElement_Sequence(string $seqEl0, int $seqEl1, string $seqEl2): self', $files[2]);

        $this->assertContains('dir/PhpNs/Ns1P/ComplexTypeWithSequenceWithChoiceAndElement.php', $files[3]);
        $this->assertContains('final class ComplexTypeWithSequenceWithChoiceAndElement', $files[3]);
        $this->assertContains('public function __construct(ComplexTypeWithSequenceWithChoiceAndElement_Choice $complexTypeWithSequenceWithChoiceAndElement_Choice, string $elementWithinSequence = null)', $files[3]);

        $this->assertContains('dir/PhpNs/Ns1P/StringType.php', $files[4]);
        $this->assertContains('final class StringType', $files[4]);

        $this->assertContains('dir/PhpNs/Ns1P/IntType.php', $files[5]);
        $this->assertContains('final class IntType', $files[5]);

        $this->evalFiles($files);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<seqWithChoice xmlns="http://example.org/schema">
    <elementWithinChoice>7</elementWithinChoice>
    <elementWithinSequence>random</elementWithinSequence>
</seqWithChoice>';
        $this->validateXmlSchema($xml, $xsdString);

        $parser = new PrimitiveXmlParser($xsd, $resolver, new NullLogger());
        $root = $parser->parse($xml);

        $this->assertSame('random', $root->getElementWithinSequence());
        $this->assertSame(7, $root->getComplexTypeWithSequenceWithChoiceAndElement_Choice()->getElementWithinChoice());
    }

    public function testComplexTypeWithSequenceWithUnboundedChoice()
    {
        $xsdString = $this->fixture('xsd/complexTypeWithSequenceWithUnboundedChoice.xsd');
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings([$xsdString]);

        $writer = new MemoryFilesystem('dir');
        $resolver = new ConstantNamespaceResolver('PhpNs\\Ns2P');
        $generator = new PrimitivePhpGenerator($writer, $resolver, new NullLogger(), new Counter());
        $generator->generate($xsd);
        $files = $writer->getFiles();
        $schema = array_pop($files);

        $this->assertCount(6, $files);
        $this->assertContains('dir/schema.php', $schema);

        $this->assertContains('dir/PhpNs/Ns2P/ComplexTypeWithSequenceWithUnboundedChoice_Choice.php', $files[0]);
        $this->assertContains('final class ComplexTypeWithSequenceWithUnboundedChoice_Choice', $files[0]);
        $this->assertContains('private function __construct', $files[0]);
        $this->assertContains('public static function createFromSeqEl0(string $seqEl0): self', $files[0]);
        $this->assertContains('public static function createFromSeqEl1(int $seqEl1): self', $files[0]);

        $this->assertContains('dir/PhpNs/Ns2P/SeqWithUnboundedChoice.php', $files[1]);
        $this->assertContains('final class SeqWithUnboundedChoice', $files[1]);
        $this->assertContains('public function __construct(string $version, array $complexTypeWithSequenceWithUnboundedChoice_Choice)', $files[1]);

        $this->assertSame($files[0], $files[2]);

        $this->assertContains('dir/PhpNs/Ns2P/ComplexTypeWithSequenceWithUnboundedChoice.php', $files[3]);
        $this->assertContains('final class ComplexTypeWithSequenceWithUnboundedChoice', $files[3]);
        // FIXME: add this check!
        // $this->assertContains('$this->checkCollection($complexTypeWithSequenceWithUnboundedChoice_Choice, ComplexTypeWithSequenceWithUnboundedChoice_Choice::class);', $files[2]);

        $this->assertContains('dir/PhpNs/Ns2P/StringType.php', $files[4]);
        $this->assertContains('final class StringType', $files[4]);

        $this->assertContains('dir/PhpNs/Ns2P/IntType.php', $files[5]);
        $this->assertContains('final class IntType', $files[5]);

        $this->evalFiles($files);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<seqWithUnboundedChoice xmlns="http://example.org/schema" version="1.0">
    <seqEl0>random</seqEl0>
    <seqEl0>other</seqEl0>
</seqWithUnboundedChoice>';
        $this->validateXmlSchema($xml, $xsdString);

        $parser = new PrimitiveXmlParser($xsd, $resolver, new NullLogger());
        $root = $parser->parse($xml);

        $this->assertCount(2, $root->getComplexTypeWithSequenceWithUnboundedChoice_Choice());
        $this->assertSame('random', $root->getComplexTypeWithSequenceWithUnboundedChoice_Choice()[0]->getSeqEl0());
        $this->assertNull($root->getComplexTypeWithSequenceWithUnboundedChoice_Choice()[0]->getSeqEl1());
        $this->assertSame('other', $root->getComplexTypeWithSequenceWithUnboundedChoice_Choice()[1]->getSeqEl0());
        $this->assertNull($root->getComplexTypeWithSequenceWithUnboundedChoice_Choice()[1]->getSeqEl1());
    }

    public function testComplexTypeWithSequenceWithElementThatIsComplexTypeWithChoice()
    {
        $xsdString = $this->fixture('xsd/complexTypeWithSequenceWithElementThatIsComplexTypeWithChoice.xsd');
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings([$xsdString]);

        $writer = new MemoryFilesystem('dir');
        $resolver = new ConstantNamespaceResolver('PhpNs\\Ns3P');
        $generator = new PrimitivePhpGenerator($writer, $resolver, new NullLogger(), new Counter());
        $generator->generate($xsd);
        $files = $writer->getFiles();
        $schema = array_pop($files);

        $this->assertCount(5, $files);
        $this->assertContains('dir/schema.php', $schema);

        $this->assertContains('dir/PhpNs/Ns3P/Root.php', $files[0]);
        $this->assertContains('final class Root', $files[0]);

        $this->assertContains('dir/PhpNs/Ns3P/ElementWithChoice.php', $files[1]);
        $this->assertContains('final class ElementWithChoice', $files[1]);
        $this->assertContains('private function __construct', $files[1]);
        $this->assertContains('public static function createFromChoEl0(int $choEl0): self', $files[1]);
        $this->assertContains('public static function createFromChoEl1(string $choEl1): self', $files[1]);
        $this->assertContains('public function getChoEl0() {', $files[1]);
        $this->assertContains('public function getChoEl1() {', $files[1]);

        $this->assertContains('dir/PhpNs/Ns3P/RootElement.php', $files[2]);
        $this->assertContains('final class RootElement', $files[2]);

        $this->assertContains('dir/PhpNs/Ns3P/StringType.php', $files[3]);
        $this->assertContains('final class StringType', $files[3]);

        $this->assertContains('dir/PhpNs/Ns3P/IntType.php', $files[4]);
        $this->assertContains('final class IntType', $files[4]);


        foreach($files as $file) {
            $class = implode("\n", array_slice(explode("\n", $file), 2));
            eval($class);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<root xmlns="http://example.org/schema" version="10.5">
    <choiceElement>
        <choEl1>random</choEl1>
    </choiceElement>
</root>';
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $dom->schemaValidateSource($xsdString);
        $parser = new PrimitiveXmlParser($xsd, $resolver, new NullLogger());
        $root = $parser->parse($xml);

        $this->assertNull($root->getChoiceElement()->getChoEl0());
        $this->assertSame('random', $root->getChoiceElement()->getChoEl1());
    }

    public function testComplexTypeWithUnboundedSequenceWithSequenceWithSequence()
    {
        $xsdString = $this->fixture('xsd/complexTypeWithUnboundedSequenceWithSequenceWithSequence.xsd');
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings([$xsdString]);

        $writer = new MemoryFilesystem('dir');
        $resolver = new ConstantNamespaceResolver('PhpNs\\Ns4P');
        $generator = new PrimitivePhpGenerator($writer, $resolver, new NullLogger(), new Counter());
        $generator->generate($xsd);
        $files = $writer->getFiles();
        $schema = array_pop($files);

        $this->assertCount(7, $files);
        $this->assertContains('dir/schema.php', $schema);

        $this->assertMultiContains([
            $files[0] => [
                'dir/PhpNs/Ns4P/Root.php',
                'final class Root',
                'public function __construct(bool $mandatory, array $seqWithEl)',
            ],
            $files[1] => [
                'dir/PhpNs/Ns4P/SequenceWithUnboundedElement.php',
                'final class SequenceWithUnboundedElement', 'public function __construct(bool $mandatory, array $seqWithEl)',
            ],
            $files[2] => [
                'dir/PhpNs/Ns4P/SequenceWithElement.php',
                'final class SequenceWithElement',
                'public function __construct(string $seqWithElAttr0, string $seqWithElAttr1, SequenceWithSequence $seqWithSeq = null)',
            ],
            $files[3] => [
                'dir/PhpNs/Ns4P/SequenceWithSequence_Sequence.php',
                'final class SequenceWithSequence_Sequence',
                'public function __construct(string $seqWithSeqEl0, int $seqWithSeqEl1, string $seqWithSeqEl2 = null)',
            ],
            $files[4] => [
                'dir/PhpNs/Ns4P/SequenceWithSequence.php',
                'final class SequenceWithSequence',
                'public function __construct(SequenceWithSequence_Sequence $SequenceWithSequence_Sequence)',
            ],
            $files[5] => ['dir/PhpNs/Ns4P/StringType.php', 'final class StringType'],
            $files[6] => ['dir/PhpNs/Ns4P/IntType.php', 'final class IntType'],
        ]);

        foreach($files as $file) {
            eval(implode("\n", array_slice(explode("\n", $file), 2)));
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<root xmlns="http://example.org/schema" mandatory="false">
    <seqWithEl seqWithElAttr0="random" seqWithElAttr1="other">
        <seqWithSeq>
            <seqWithSeqEl0>fffff</seqWithSeqEl0>
            <seqWithSeqEl1>7</seqWithSeqEl1>
            <seqWithSeqEl2>xxxxx</seqWithSeqEl2>
        </seqWithSeq>
    </seqWithEl>
</root>';
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $dom->schemaValidateSource($xsdString);
        $parser = new PrimitiveXmlParser($xsd, $resolver, new NullLogger());
        $root = $parser->parse($xml);

        $this->assertSame('random', $root->getSeqWithEl()[0]->getSeqWithElAttr0());
        $this->assertSame('other', $root->getSeqWithEl()[0]->getSeqWithElAttr1());
        $this->assertSame('fffff', $root->getSeqWithEl()[0]->getSeqWithSeq()->getSequenceWithSequence_Sequence()->getSeqWithSeqEl0());
        $this->assertSame(7, $root->getSeqWithEl()[0]->getSeqWithSeq()->getSequenceWithSequence_Sequence()->getSeqWithSeqEl1());
        $this->assertSame('xxxxx', $root->getSeqWithEl()[0]->getSeqWithSeq()->getSequenceWithSequence_Sequence()->getSeqWithSeqEl2());
    }

    public function testRootElementWithSimpleTypeWithRestrictions()
    {
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings([
            $this->fixture('xsd/rootElementWithSimpleTypeWithRestrictions.xsd')
        ]);

        $filesystem = new MemoryFilesystem('dir');
        $resolver = new ConstantNamespaceResolver('PhpNs\\PrimitiveSimpleTypes');
        $logger = new NullLogger();
        $generator = new PrimitivePhpGenerator($filesystem, $resolver, $logger, new Counter());
        $generator->generate($xsd);
        $files = $filesystem->getFiles();
        array_pop($files);
        $this->evalFiles($files);

        $xml = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<length5 xmlns="http://example.org/schema/simpleTypes">abcde</length5>
EOF;

        $parser = new PrimitiveXmlParser($xsd, $resolver, $logger);

        $this->assertSame('abcde', $parser->parse($xml));
    }

    public function testComplexTypeWithElementWithSimpleContentExtension()
    {
        $xsdString = $this->fixture('xsd/complexTypeWithExtensions.xsd');
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings([$xsdString]);

        $filesystem = new MemoryFilesystem('dir');
        $resolver = new ConstantNamespaceResolver('PhpNs\\SimpleContentExtension');
        $logger = new NullLogger();
        $generator = new PrimitivePhpGenerator($filesystem, $resolver, $logger, new Counter());
        $generator->generate($xsd);
        $files = $filesystem->getFiles();
        array_pop($files);
        $this->evalFiles($files);

        $xml = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<root xmlns="http://example.org/schema">
    <item type="first" />
</root>
EOF;
        $this->validateXmlSchema($xml, $xsdString);

        $parser = new PrimitiveXmlParser($xsd, $resolver, $logger);
        $parsed = $parser->parse($xml);

        $this->assertCount(1, $parsed->getItem());
        $this->assertSame('first', $parsed->getItem()[0]->getType());
    }

    public function testComplexTypeWithComplexContentExtension()
    {
        $xsdString = $this->fixture('xsd/complexTypeWithExtensions.xsd');
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings([$xsdString]);

        $filesystem = new MemoryFilesystem('dir');
        $resolver = new ConstantNamespaceResolver('PhpNs\\ComplexContentExtension');
        $logger = new NullLogger();
        $generator = new PrimitivePhpGenerator($filesystem, $resolver, $logger, new Counter());
        $generator->generate($xsd);
        $files = $filesystem->getFiles();
        array_pop($files);
        $this->evalFiles($files);

        $xml = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<random xmlns="http://example.org/schema">
    <item type="first" />
    <value>xxx</value>
</random>
EOF;
        $this->validateXmlSchema($xml, $xsdString);

        $parser = new PrimitiveXmlParser($xsd, $resolver, $logger);
        $parsed = $parser->parse($xml);

        $this->assertCount(1, $parsed->getItem());
        $this->assertSame('first', $parsed->getItem()[0]->getType());
        $this->assertSame('xxx', $parsed->getValue());
    }
}
