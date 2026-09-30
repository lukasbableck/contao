<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Controller\ContentElement;

use Contao\CoreBundle\Controller\ContentElement\ImagesController;
use Contao\CoreBundle\Routing\ResponseContext\JsonLd\JsonLdManager;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContext;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

class ImagesControllerTest extends ContentElementTestCase
{
    private ResponseContextAccessor|null $responseContextAccessor = null;

    public function testOutputsSingleImage(): void
    {
        $security = $this->createMock(Security::class);

        $response = $this->renderWithModelData(
            new ImagesController($security, $this->getDefaultStorage(), $this->getDefaultStudio(), ['jpg']),
            [
                'type' => 'image',
                'singleSRC' => StringUtil::uuidToBin(ContentElementTestCase::FILE_IMAGE1),
                'sortBy' => '',
                'numberOfItems' => '0',
                'size' => '',
                'fullsize' => true,
                'perPage' => '4',
                'perRow' => '2',
            ],
        );

        $expectedOutput = <<<'HTML'
            <div class="content-image">
                <figure>
                    <img src="files/image1.jpg" alt>
                </figure>
            </div>
            HTML;

        $this->assertSameHtml($expectedOutput, $response->getContent());
    }

    public function testOutputsGallery(): void
    {
        $security = $this->createMock(Security::class);

        $response = $this->renderWithModelData(
            new ImagesController($security, $this->getDefaultStorage(), $this->getDefaultStudio(), ['jpg']),
            [
                'type' => 'gallery',
                'multiSRC' => serialize([
                    StringUtil::uuidToBin(ContentElementTestCase::FILE_IMAGE1),
                    StringUtil::uuidToBin(ContentElementTestCase::FILE_IMAGE2),
                    StringUtil::uuidToBin(ContentElementTestCase::FILE_IMAGE3),
                ]),
                'sortBy' => 'name_desc',
                'numberOfItems' => 2,
                'size' => '',
                'fullsize' => true,
                'perPage' => 4,
                'perRow' => 2,
            ],
        );

        $expectedOutput = <<<'HTML'
            <div class="content-gallery content-gallery--cols-2">
                <ul>
                    <li>
                        <figure>
                            <img src="files/image3.jpg" alt>
                        </figure>
                    </li>
                    <li>
                        <figure>
                            <img src="files/image2.jpg" alt>
                        </figure>
                    </li>
                </ul>
            </div>
            HTML;

        $this->assertSameHtml($expectedOutput, $response->getContent());
    }

    public function testIgnoresInvalidTypes(): void
    {
        $security = $this->createMock(Security::class);

        $response = $this->renderWithModelData(
            new ImagesController($security, $this->getDefaultStorage(), $this->getDefaultStudio(), ['svg', 'jpg', 'png']),
            [
                'type' => 'gallery',
                'multiSRC' => serialize([
                    StringUtil::uuidToBin(ContentElementTestCase::FILE_IMAGE1),
                    StringUtil::uuidToBin(ContentElementTestCase::FILE_VIDEO_MP4),
                ]),
                'sortBy' => 'name_desc',
                'numberOfItems' => 0,
                'size' => '',
                'fullsize' => true,
                'perPage' => 1,
                'perRow' => 1,
            ],
        );

        $expectedOutput = <<<'HTML'
            <div class="content-gallery content-gallery--cols-1">
                <ul>
                    <li>
                        <figure>
                            <img src="files/image1.jpg" alt>
                        </figure>
                    </li>
                </ul>
            </div>
            HTML;

        $this->assertSameHtml($expectedOutput, $response->getContent());
    }

    /**
     * @dataProvider provideGalleryHeadlines
     */
    public function testAddsGallerySchema(string|null $headline, string|null $expectedName): void
    {
        $manager = $this->initializeJsonLdManager();

        $this->renderWithModelData(
            new ImagesController(
                $this->createStub(Security::class),
                $this->getDefaultStorage(),
                $this->getDefaultStudio(),
                ['jpg'],
                $this->responseContextAccessor,
                new HtmlDecoder($this->getDefaultInsertTagParser()),
            ),
            [
                'id' => 42,
                'type' => 'gallery',
                'headline' => $headline,
                'multiSRC' => serialize([
                    StringUtil::uuidToBin(self::FILE_IMAGE1),
                    StringUtil::uuidToBin(self::FILE_IMAGE2),
                    StringUtil::uuidToBin(self::FILE_IMAGE3),
                ]),
                'sortBy' => 'name_desc',
                'numberOfItems' => 2,
                'fullsize' => false,
            ],
            request: Request::create('https://example.com/gallery?foo=bar'),
        );

        $gallery = [
            '@type' => 'ImageGallery',
            'url' => 'https://example.com/gallery?foo=bar',
            'image' => [
                ['@id' => 'files/image3.jpg'],
                ['@id' => 'files/image2.jpg'],
            ],
            '@id' => '#/schema/gallery/42',
        ];

        if (null !== $expectedName) {
            $gallery['name'] = $expectedName;
        }

        $graph = $manager->getGraphForSchema(JsonLdManager::SCHEMA_ORG)->toArray();
        $galleries = array_values(array_filter($graph['@graph'], static fn (array $schema): bool => 'ImageGallery' === $schema['@type']));

        $this->assertCount(1, $galleries);
        $this->assertEquals($gallery, $galleries[0]);

        $images = array_values(array_filter($graph['@graph'], static fn (array $schema): bool => 'ImageObject' === $schema['@type']));

        $this->assertEquals([
            ['@type' => 'ImageObject', 'contentUrl' => 'files/image3.jpg', '@id' => 'files/image3.jpg'],
            ['@type' => 'ImageObject', 'contentUrl' => 'files/image2.jpg', '@id' => 'files/image2.jpg'],
        ], $images);
    }

    public static function provideGalleryHeadlines(): iterable
    {
        yield 'decoded headline' => [serialize(['unit' => 'h2', 'value' => '<em>Images</em> &amp; {{demo}}']), 'Images & demo'];
        yield 'missing headline' => [null, null];
    }

    public function testDoesNotOutputAnythingWithoutImages(): void
    {
        $security = $this->createMock(Security::class);

        $response = $this->renderWithModelData(
            new ImagesController($security, $this->getDefaultStorage(), $this->getDefaultStudio(), ['jpg']),
            [
                'type' => 'image',
                'singleSRC' => null,
                'sortBy' => 'name_desc',
                'fullsize' => true,
            ],
        );

        $this->assertSame('', $response->getContent());

        $response = $this->renderWithModelData(
            new ImagesController($security, $this->getDefaultStorage(), $this->getDefaultStudio(), ['jpg']),
            [
                'type' => 'gallery',
                'multiSRC' => null,
                'sortBy' => 'name_desc',
                'fullsize' => true,
            ],
        );

        $this->assertSame('', $response->getContent());
    }

    public function testIgnoresMissingImages(): void
    {
        $security = $this->createMock(Security::class);

        $response = $this->renderWithModelData(
            new ImagesController($security, $this->getDefaultStorage(), $this->getDefaultStudio(), ['svg', 'jpg', 'png']),
            [
                'type' => 'gallery',
                'multiSRC' => serialize([
                    StringUtil::uuidToBin(ContentElementTestCase::FILE_IMAGE1),
                    StringUtil::uuidToBin(ContentElementTestCase::FILE_IMAGE_MISSING),
                ]),
                'sortBy' => 'name_desc',
                'numberOfItems' => 0,
                'size' => '',
                'fullsize' => true,
                'perPage' => 1,
                'perRow' => 1,
            ],
        );

        $expectedOutput = <<<'HTML'
            <div class="content-gallery content-gallery--cols-1">
                <ul>
                    <li>
                        <figure>
                            <img src="files/image1.jpg" alt>
                        </figure>
                    </li>
                </ul>
            </div>
            HTML;

        $this->assertSameHtml($expectedOutput, $response->getContent());
    }

    protected function getResponseContextAccessor(): ResponseContextAccessor
    {
        return $this->responseContextAccessor ?? parent::getResponseContextAccessor();
    }

    private function initializeJsonLdManager(): JsonLdManager
    {
        $context = new ResponseContext();
        $manager = new JsonLdManager($context);
        $context->add($manager);
        $this->responseContextAccessor = $this->createStub(ResponseContextAccessor::class);
        $this->responseContextAccessor
            ->method('getResponseContext')
            ->willReturn($context)
        ;

        return $manager;
    }
}
