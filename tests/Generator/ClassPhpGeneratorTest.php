<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Analyzer\XsdAnalyzer;
use Thunder\Xsdragon\Generator\ClassPhpGenerator;
use Thunder\Xsdragon\Logger\NullLogger;
use Thunder\Xsdragon\NamespaceResolver\ConstantNamespaceResolver;
use Thunder\Xsdragon\Parser\ClassXmlParser;
use Thunder\Xsdragon\Filesystem\MemoryFilesystem;
use Thunder\Xsdragon\Serializer\ClassDomXmlSerializer;
use Thunder\Xsdragon\Serializer\ClassStringXmlSerializer;
use Thunder\Xsdragon\Tests\Traits\XsdragonTestTrait;

final class ClassPhpGeneratorTest extends TestCase
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
        $resolver = new ConstantNamespaceResolver('PhpNs\\Ns5');
        $generator = new ClassPhpGenerator($filesystem, $resolver, new NullLogger());
        $generator->generate($xsd);
        $files = $filesystem->getFiles();
        $schema = array_pop($files);

        $this->assertCount(7, $files);
        $this->assertContains('dir/schema.php', $schema);

        $this->assertContains('dir/PhpNs/Ns5/Root.php', $files[0]);
        $this->assertContains('dir/PhpNs/Ns5/RootType.php', $files[1]);
        $this->assertContains('dir/PhpNs/Ns5/FirstType.php', $files[2]);
        $this->assertContains('dir/PhpNs/Ns5/SecondType.php', $files[3]);
        $this->assertContains('dir/PhpNs/Ns5/ThirdType.php', $files[4]);
        $this->assertContains('dir/PhpNs/Ns5/FourthType.php', $files[5]);
        $this->assertContains('dir/PhpNs/Ns5/StringType.php', $files[6]);
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
        $parser = new ClassXmlParser($xsd, $resolver, new NullLogger());
        $root = $parser->parse($xml);
        $this->assertXmlStringEqualsXmlString($xml, $root->toXml());
        $this->assertXmlStringEqualsXmlString($xml, (new ClassStringXmlSerializer($xsd))->serialize($root));
        $this->assertXmlStringEqualsXmlString($xml, (new ClassDomXmlSerializer($xsd))->serialize($root));

        $parser = new ClassXmlParser($xsd, $resolver, new NullLogger());
        $root = $parser->parse($xml);

        $this->assertSame('value', $root->getType()->getFirst()->getSecond()->getThird()->getFourth()->getSimple()->getValue());
    }

    public function testComplexTypeWithSequenceWithChoiceBetweenElementAndSequence()
    {
        $xsdString = $this->fixture('xsd/complexTypeWithSequenceWithChoiceBetweenElementAndSequence.xsd');
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings([$xsdString]);

        $writer = new MemoryFilesystem('dir');
        $resolver = new ConstantNamespaceResolver('PhpNs\\Ns1');
        $generator = new ClassPhpGenerator($writer, $resolver, new NullLogger());
        $generator->generate($xsd);
        $files = $writer->getFiles();
        $schema = array_pop($files);

        $this->assertCount(5, $files);
        $this->assertContains('dir/schema.php', $schema);

        $this->assertContains('dir/PhpNs/Ns1/SeqWithChoice.php', $files[0]);
        $this->assertContains('final class SeqWithChoice', $files[0]);
        $this->assertContains('public function __construct(ComplexTypeWithSequenceWithChoiceAndElement $type)', $files[0]);

        $this->assertContains('dir/PhpNs/Ns1/StringType.php', $files[1]);
        $this->assertContains('final class StringType', $files[1]);

        $this->assertContains('dir/PhpNs/Ns1/IntType.php', $files[2]);
        $this->assertContains('final class IntType', $files[2]);

        $this->assertContains('dir/PhpNs/Ns1/ComplexTypeWithSequenceWithChoiceAndElement_Choice.php', $files[3]);
        $this->assertContains('final class ComplexTypeWithSequenceWithChoiceAndElement_Choice', $files[3]);
        $this->assertContains('public static function createFromElementWithinChoice(IntType $elementWithinChoice): self', $files[3]);
        $this->assertContains('public static function createFromComplexTypeWithSequenceWithChoiceAndElement(StringType $seqEl0 = null, IntType $seqEl1 = null, StringType $seqEl2 = null): self', $files[3]);

        $this->assertContains('dir/PhpNs/Ns1/ComplexTypeWithSequenceWithChoiceAndElement.php', $files[4]);
        $this->assertContains('final class ComplexTypeWithSequenceWithChoiceAndElement', $files[4]);
        $this->assertContains('public function __construct(
        ComplexTypeWithSequenceWithChoiceAndElement_Choice $complexTypeWithSequenceWithChoiceAndElement_Choice,
        StringType $elementWithinSequence = null
    ) {', $files[4]);

        foreach($files as $file) {
            eval(implode("\n", array_slice(explode("\n", $file), 2)));
        }

        $parser = new ClassXmlParser($xsd, $resolver, new NullLogger());
        $root = $parser->parse('<?xml version="1.0" encoding="UTF-8"?>
<seqWithChoice xmlns="http://example.org/schema">
    <elementWithinChoice>7</elementWithinChoice>
    <elementWithinSequence>random</elementWithinSequence>
</seqWithChoice>');

        $this->assertSame('random', $root->getType()->getElementWithinSequence()->getValue());
        $this->assertSame(7, $root->getType()->getComplexTypeWithSequenceWithChoiceAndElement_Choice()->getElementWithinChoice()->getValue());
    }

    public function testComplexTypeWithSequenceWithUnboundedChoice()
    {
        $xsdString = $this->fixture('xsd/complexTypeWithSequenceWithUnboundedChoice.xsd');
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings([$xsdString]);

        $writer = new MemoryFilesystem('dir');
        $resolver = new ConstantNamespaceResolver('PhpNs\\Ns2');
        $generator = new ClassPhpGenerator($writer, $resolver, new NullLogger());
        $generator->generate($xsd);
        $files = $writer->getFiles();
        $schema = array_pop($files);

        $this->assertCount(5, $files);
        $this->assertContains('dir/schema.php', $schema);

        $this->assertContains('dir/PhpNs/Ns2/SeqWithUnboundedChoice.php', $files[0]);
        $this->assertContains('final class SeqWithUnboundedChoice', $files[0]);

        $this->assertContains('dir/PhpNs/Ns2/StringType.php', $files[1]);
        $this->assertContains('final class StringType', $files[1]);

        $this->assertContains('dir/PhpNs/Ns2/IntType.php', $files[2]);
        $this->assertContains('final class IntType', $files[2]);

        $this->assertContains('dir/PhpNs/Ns2/ComplexTypeWithSequenceWithUnboundedChoice_Choice.php', $files[3]);
        $this->assertContains('final class ComplexTypeWithSequenceWithUnboundedChoice_Choice', $files[3]);
        $this->assertContains('private function __construct', $files[3]);
        $this->assertContains('public static function createFromSeqEl0(StringType $seqEl0): self', $files[3]);
        $this->assertContains('public static function createFromSeqEl1(IntType $seqEl1): self', $files[3]);

        $this->assertContains('dir/PhpNs/Ns2/ComplexTypeWithSequenceWithUnboundedChoice.php', $files[4]);
        $this->assertContains('final class ComplexTypeWithSequenceWithUnboundedChoice', $files[4]);
        $this->assertContains('$this->checkCollection($complexTypeWithSequenceWithUnboundedChoice_Choice, ComplexTypeWithSequenceWithUnboundedChoice_Choice::class);', $files[4]);

        foreach($files as $file) {
            eval(implode("\n", array_slice(explode("\n", $file), 2)));
        }

        $parser = new ClassXmlParser($xsd, $resolver, new NullLogger());
        $root = $parser->parse('<?xml version="1.0" encoding="UTF-8"?>
<seqWithUnboundedChoice xmlns="http://example.org/schema">
    <seqEl0>random</seqEl0>
    <seqEl0>other</seqEl0>
</seqWithUnboundedChoice>');

        $this->assertSame('random', $root->getType()->getComplexTypeWithSequenceWithUnboundedChoice_Choice()[0]->getSeqEl0()->getValue());
        $this->assertSame('other', $root->getType()->getComplexTypeWithSequenceWithUnboundedChoice_Choice()[1]->getSeqEl0()->getValue());
    }

    public function testComplexTypeWithSequenceWithElementThatIsComplexTypeWithChoice()
    {
        $xsdString = $this->fixture('xsd/complexTypeWithSequenceWithElementThatIsComplexTypeWithChoice.xsd');
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings([$xsdString]);

        $writer = new MemoryFilesystem('dir');
        $resolver = new ConstantNamespaceResolver('PhpNs\\Ns3');
        $generator = new ClassPhpGenerator($writer, $resolver, new NullLogger());
        $generator->generate($xsd);
        $files = $writer->getFiles();
        $schema = array_pop($files);

        $this->assertCount(5, $files);
        $this->assertContains('dir/schema.php', $schema);

        $this->assertContains('dir/PhpNs/Ns3/Root.php', $files[0]);
        $this->assertContains('final class Root', $files[0]);

        $this->assertContains('dir/PhpNs/Ns3/StringType.php', $files[1]);
        $this->assertContains('final class StringType', $files[1]);

        $this->assertContains('dir/PhpNs/Ns3/IntType.php', $files[2]);
        $this->assertContains('final class IntType', $files[2]);

        $this->assertContains('dir/PhpNs/Ns3/ElementWithChoice.php', $files[3]);
        $this->assertContains('final class ElementWithChoice', $files[3]);
        $this->assertContains('private function __construct', $files[3]);
        $this->assertContains('public static function createFromChoEl0(IntType $choEl0): self', $files[3]);
        $this->assertContains('public static function createFromChoEl1(StringType $choEl1): self', $files[3]);
        $this->assertContains('public function getChoEl0() {', $files[3]);
        $this->assertContains('public function getChoEl1() {', $files[3]);

        $this->assertContains('dir/PhpNs/Ns3/RootElement.php', $files[4]);
        $this->assertContains('final class RootElement', $files[4]);


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
        $parser = new ClassXmlParser($xsd, $resolver, new NullLogger());
        $root = $parser->parse($xml);

        $this->assertNull($root->getType()->getChoiceElement()->getChoEl0());
        $this->assertSame('random', $root->getType()->getChoiceElement()->getChoEl1()->getValue());
    }

    public function testComplexTypeWithUnboundedSequenceWithSequenceWithSequence()
    {
        $xsdString = $this->fixture('xsd/complexTypeWithUnboundedSequenceWithSequenceWithSequence.xsd');
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings([$xsdString]);

        $writer = new MemoryFilesystem('dir');
        $resolver = new ConstantNamespaceResolver('PhpNs\\Ns4');
        $generator = new ClassPhpGenerator($writer, $resolver, new NullLogger());
        $generator->generate($xsd);
        $files = $writer->getFiles();
        $schema = array_pop($files);

        $this->assertCount(7, $files);
        $this->assertContains('dir/schema.php', $schema);

        $this->assertMultiContains([
            $files[0] => ['dir/PhpNs/Ns4/Root.php', 'final class Root', 'public function __construct(SequenceWithUnboundedElement $type)'],
            $files[1] => ['dir/PhpNs/Ns4/StringType.php', 'final class StringType'],
            $files[2] => ['dir/PhpNs/Ns4/IntType.php', 'final class IntType'],
            $files[3] => ['dir/PhpNs/Ns4/SequenceWithUnboundedElement.php', 'final class SequenceWithUnboundedElement', 'public function __construct(
        bool $mandatory,
        array $seqWithEl
    ) {'],
            $files[4] => ['dir/PhpNs/Ns4/SequenceWithElement.php', 'final class SequenceWithElement', 'public function __construct(
        string $seqWithElAttr0,
        string $seqWithElAttr1,
        SequenceWithSequence $seqWithSeq = null
    ) {'],
            $files[5] => ['dir/PhpNs/Ns4/SequenceWithSequence_Sequence.php', 'final class SequenceWithSequence_Sequence', 'public function __construct(
        StringType $seqWithSeqEl0,
        IntType $seqWithSeqEl1,
        StringType $seqWithSeqEl2 = null
    ) {'],
            $files[6] => ['dir/PhpNs/Ns4/SequenceWithSequence.php', 'final class SequenceWithSequence', 'public function __construct(
        SequenceWithSequence_Sequence $sequenceWithSequence_Sequence
    ) {'],
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
        $parser = new ClassXmlParser($xsd, $resolver, new NullLogger());
        $root = $parser->parse($xml);

        $this->assertSame('random', $root->getType()->getSeqWithEl()[0]->getSeqWithElAttr0());
        $this->assertSame('other', $root->getType()->getSeqWithEl()[0]->getSeqWithElAttr1());
        $this->assertSame('fffff', $root->getType()->getSeqWithEl()[0]->getSeqWithSeq()->getSequenceWithSequence_Sequence()->getSeqWithSeqEl0()->getValue());
        $this->assertSame(7, $root->getType()->getSeqWithEl()[0]->getSeqWithSeq()->getSequenceWithSequence_Sequence()->getSeqWithSeqEl1()->getValue());
        $this->assertSame('xxxxx', $root->getType()->getSeqWithEl()[0]->getSeqWithSeq()->getSequenceWithSequence_Sequence()->getSeqWithSeqEl2()->getValue());
    }
}
