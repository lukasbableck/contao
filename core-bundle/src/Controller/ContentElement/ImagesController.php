<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Filesystem\FilesystemItem;
use Contao\CoreBundle\Filesystem\FilesystemUtil;
use Contao\CoreBundle\Filesystem\SortMode;
use Contao\CoreBundle\Filesystem\VirtualFilesystem;
use Contao\CoreBundle\Image\Studio\Figure;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\Routing\ResponseContext\JsonLd\JsonLdManager;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement('image', category: 'media')]
#[AsContentElement('gallery', category: 'media')]
class ImagesController extends AbstractContentElementController
{
    public function __construct(
        private readonly Security $security,
        private readonly VirtualFilesystem $filesStorage,
        private readonly Studio $studio,
        private readonly array $validExtensions,
        private readonly ResponseContextAccessor|null $responseContextAccessor = null,
        private readonly HtmlDecoder|null $htmlDecoder = null,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        // Find all images (see #5911)
        $filesystemItems = FilesystemUtil::listContentsFromSerialized($this->filesStorage, $this->getSources($model))
            ->filter(fn ($item) => \in_array($item->getExtension(true), $this->validExtensions, true))
        ;

        // Sort elements; relay to client-side logic if list should be randomized
        if ($sortMode = SortMode::tryFrom($model->sortBy)) {
            $filesystemItems = $filesystemItems->sort($sortMode);
        }

        $template->set('sort_mode', $sortMode);
        $template->set('randomize_order', $randomize = 'random' === $model->sortBy);

        // Limit elements; use client-side logic for only displaying the first $limit
        // elements in case we are dealing with a random order
        if ($model->numberOfItems > 0 && !$randomize) {
            $filesystemItems = $filesystemItems->limit($model->numberOfItems);
        }

        $template->set('limit', $model->numberOfItems > 0 && $randomize ? $model->numberOfItems : null);

        // Compile list of images
        $figureBuilder = $this->studio
            ->createFigureBuilder()
            ->setSize($model->size)
            ->setLightboxGroupIdentifier('lb'.$model->id)
            ->enableLightbox($model->fullsize)
        ;

        if ('image' === $model->type) {
            $figureBuilder->setOverwriteMetadata($model->getOverwriteMetadata());
        }

        $imageList = array_filter(array_map(
            fn (FilesystemItem $filesystemItem): Figure|null => $figureBuilder
                ->fromStorage($this->filesStorage, $filesystemItem->getPath())
                ->buildIfResourceExists(),
            iterator_to_array($filesystemItems),
        ));

        if (!$imageList) {
            return new Response();
        }

        $template->set('images', $imageList);
        $template->set('items_per_page', $model->perPage ?: null);
        $template->set('items_per_row', $model->perRow ?: null);

        $response = $template->getResponse();

        if ('gallery' === $model->type) {
            $this->addGallerySchema($model, $request, $imageList);
        }

        return $response;
    }

    /**
     * @param array<Figure> $images
     */
    private function addGallerySchema(ContentModel $model, Request $request, array $images): void
    {
        $responseContext = $this->responseContextAccessor?->getResponseContext();

        if (!$responseContext?->has(JsonLdManager::class) || !$this->htmlDecoder) {
            return;
        }

        $jsonLdManager = $responseContext->get(JsonLdManager::class);
        $graph = $jsonLdManager->getGraphForSchema(JsonLdManager::SCHEMA_ORG);
        $galleryImages = [];

        foreach ($images as $image) {
            $galleryImages[] = ['@id' => $image->getSchemaOrgData()['identifier']];
        }

        $headline = StringUtil::deserialize($model->headline ?: '', true);
        $gallery = $jsonLdManager->createSchemaOrgTypeFromArray(array_filter([
            '@type' => 'ImageGallery',
            'name' => $this->htmlDecoder->htmlToPlainText($headline['value'] ?? ''),
            'url' => $request->getUri(),
            'associatedMedia' => $galleryImages,
        ]));
        $gallery->setProperty('@id', '#/schema/gallery/'.$model->id);

        $graph->set($gallery, '#/schema/gallery/'.$model->id);
    }

    /**
     * @return string|array<string>
     */
    private function getSources(ContentModel $model): array|string
    {
        if ('image' === $model->type) {
            return [$model->singleSRC];
        }

        if ($model->useHomeDir) {
            $user = $this->security->getUser();

            if ($user instanceof FrontendUser && $user->assignDir && $user->homeDir) {
                return $user->homeDir;
            }
        }

        return $model->multiSRC ?? [];
    }
}
