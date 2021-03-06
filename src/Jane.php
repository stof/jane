<?php

namespace Joli\Jane;

use Joli\Jane\Encoder\RawEncoder;
use Joli\Jane\Generator\Context\Context;
use Joli\Jane\Generator\ModelGenerator;
use Joli\Jane\Generator\Naming;
use Joli\Jane\Generator\NormalizerGenerator;
use Joli\Jane\Guesser\ChainGuesser;
use Joli\Jane\Guesser\JsonSchema\JsonSchemaGuesserFactory;
use Joli\Jane\Model\JsonSchema;
use Joli\Jane\Normalizer\JsonSchemaNormalizer;

use Joli\Jane\Normalizer\NormalizerFactory;
use PhpParser\PrettyPrinter\Standard;

use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\CS\Config\Config;
use Symfony\CS\Console\ConfigurationResolver;
use Symfony\CS\Finder\DefaultFinder;
use Symfony\CS\Fixer;

class Jane
{
    const VERSION = '1.0-dev';

    private $serializer;

    private $modelGenerator;

    private $normalizerGenerator;

    private $fixer;

    private $chainGuesser;

    public function __construct(Serializer $serializer, ChainGuesser $chainGuesser, ModelGenerator $modelGenerator, NormalizerGenerator $normalizerGenerator, Fixer $fixer = null)
    {
        $this->serializer          = $serializer;
        $this->chainGuesser        = $chainGuesser;
        $this->modelGenerator      = $modelGenerator;
        $this->normalizerGenerator = $normalizerGenerator;
        $this->fixer               = $fixer;
    }

    /**
     * Return a list of class guessed
     *
     * @param $schemaFilePath
     * @param $name
     * @param $namespace
     * @param $directory
     *
     * @return Context
     */
    public function createContext($schemaFilePath, $name, $namespace, $directory)
    {
        $schema  = $this->serializer->deserialize(file_get_contents($schemaFilePath), 'Joli\Jane\Model\JsonSchema', 'json');
        $classes = $this->chainGuesser->guessClass($schema, $name);

        foreach ($classes as $class) {
            $properties = $this->chainGuesser->guessProperties($class->getObject(), $name, $classes);

            foreach ($properties as $property) {
                $property->setType($this->chainGuesser->guessType($property->getObject(), $property->getName(), $classes));
            }

            $class->setProperties($properties);
        }

        return new Context($schema, $namespace, $directory, $classes);
    }

    public function generate($schemaFilePath, $name, $namespace, $directory)
    {
        $context = $this->createContext($schemaFilePath, $name, $namespace, $directory);

        if (!file_exists(($directory . DIRECTORY_SEPARATOR . 'Model'))) {
            mkdir($directory . DIRECTORY_SEPARATOR . 'Model', 0755, true);
        }

        if (!file_exists(($directory . DIRECTORY_SEPARATOR . 'Normalizer'))) {
            mkdir($directory . DIRECTORY_SEPARATOR . 'Normalizer', 0755, true);
        }

        $prettyPrinter   = new Standard();
        $modelFiles      = $this->modelGenerator->generate($context->getRootReference(), $name, $context);
        $normalizerFiles = $this->normalizerGenerator->generate($context->getRootReference(), $name, $context);
        $generated       = [];

        foreach ($modelFiles as $file) {
            $generated[] = $file->getFilename();
            file_put_contents($file->getFilename(), $prettyPrinter->prettyPrintFile([$file->getNode()]));
        }

        foreach ($normalizerFiles as $file) {
            $generated[] = $file->getFilename();
            file_put_contents($file->getFilename(), $prettyPrinter->prettyPrintFile([$file->getNode()]));
        }

        if ($this->fixer !== null) {
            $config = Config::create()
                ->setRiskyAllowed(true)
                ->setRules(array(
                    '@Symfony' => true,
                    'empty_return' => false,
                    'concat_without_spaces' => false,
                    'double_arrow_multiline_whitespaces' => false,
                    'unalign_equals' => false,
                    'unalign_double_arrow' => false,
                    'align_double_arrow' => true,
                    'align_equals' => true,
                    'concat_with_spaces' => true,
                    'newline_after_open_tag' => true,
                    'ordered_use' => true,
                    'phpdoc_order' => true,
                    'short_array_syntax' => true,
                ))
                ->finder(
                    DefaultFinder::create()
                        ->in($directory)
                )
            ;

            $resolver = new ConfigurationResolver();
            $resolver->setDefaultConfig($config);
            $resolver->resolve();

            $this->fixer->fix($config);
        }

        return $generated;
    }

    public static function build()
    {
        $serializer     = self::buildSerializer();
        $chainGuesser   = JsonSchemaGuesserFactory::create($serializer);
        $naming         = new Naming();
        $modelGenerator = new ModelGenerator($naming, $chainGuesser, $chainGuesser);
        $normGenerator  = new NormalizerGenerator($naming);
        $fixer          = new Fixer();

        return new self($serializer, $chainGuesser, $modelGenerator, $normGenerator, $fixer);
    }

    public static function buildSerializer()
    {
        $encoders       = [new JsonEncoder(new JsonEncode(JSON_UNESCAPED_SLASHES), new JsonDecode(false)), new RawEncoder()];
        $normalizers    = NormalizerFactory::create();

        return new Serializer($normalizers, $encoders);
    }
}
