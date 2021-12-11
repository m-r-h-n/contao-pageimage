<?php

declare(strict_types=1);

namespace Terminal42\PageimageBundle\Controller;

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Image;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Terminal42\PageimageBundle\PageimageHelper;
use Terminal42\PageimageBundle\PagevideoHelper;

/**
 * @FrontendModule(category="miscellaneous")
 */
class PageimageController extends AbstractFrontendModuleController
{
    /**
     * @var PageimageHelper
     */
    private $helper;

    /**
     * @var PagevideoHelper
     */
    private $videohelper;

    public function __construct(PageimageHelper $helper, PagevideoHelper $videohelper)
    {
        $this->helper = $helper;
        $this->videohelper = $videohelper;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $images = $this->getImages($model);
        $videos = $this->getVideos($model);

        if (empty($images) && empty($videos)) {
            return new Response();
        }

        //print_r($videos);

        $templateData = [];
        $templateDataVideos = [];

        foreach ($images as $image) {
            $templateData[] = $this->generateImage($image, $model);
        }

        foreach ($videos as $video) {
            //$templateDataVideos[] = $this->generateVideo($video, $model);
            $templateDataVideos[] = $video;
        }

        $size = StringUtil::deserialize($model->imgSize);
        $image['src'] = Image::get($image['path'], $size[0], $size[1], $size[2]);
        $template->setData(array_merge($template->getData(), $templateData[0]));

        $template->allImages = $templateData;
        $template->allVideos = $templateDataVideos;

        //print_r($templateData);
        // Lazy-load the media queries
        $template->mediaQueries = function () use ($templateData) {
            return $this->compileMediaQueries($templateData[0]['picture']);
        };

        return $template->getResponse();
    }

    private function getImages(ModuleModel $model): array
    {
        if ($model->defineRoot) {
            $objPage = PageModel::findByPk($model->rootPage);
        } else {
            global $objPage;
        }

        if (null === $objPage) {
            return [];
        }

        $images = $this->helper->findForPage($objPage, (bool) $model->inheritPageImage);

        if (null === $images) {
            return [];
        }

        if ($model->allPageImages) {
            return $images;
        }

        if ($model->randomPageImage) {
            $index = random_int(0, \count($images) - 1);
        } else {
            $index = $model->levelOffset;
        }

        if (!isset($images[$index])) {
            return [];
        }

        return [$images[$index]];
    }

    private function generateImage(array $image, ModuleModel $model): array
    {
        $image['singleSRC'] = $image['path'];
        $image['size'] = $model->imgSize;

        $result = new \stdClass();
        Controller::addImageToTemplate($result, $image);

        return (array) $result;
    }

    /*
    private function generateVideo(array $video, ModuleModel $model): array
    {
        $video['singleSRC'] = $video['path'];
        //$image['size'] = $model->imgSize;

        $result = new \stdClass();
        Controller::addImageToTemplate($result, $video);

        return (array) $result;
    }
    */

    private function compileMediaQueries(array $picture): array
    {
        $mediaQueries = [];
        $sources = [$picture['img']];

        if (\is_array($picture['sources'])) {
            $sources = array_merge($sources, $picture['sources']);
        }

        foreach ($sources as $value) {
            foreach (StringUtil::trimsplit(',', $value['srcset']) as $srcset) {
                [$src, $density] = StringUtil::trimsplit(' ', $srcset);

                if (null === $density) {
                    continue;
                }

                $density = rtrim($density, 'x');

                if (1 !== (int) $density || !empty($value['media'])) {
                    $mediaQueries[] = [
                        'mq' => sprintf(
                            $density > 1 ? 'screen and %1$s%2$s, screen and %1$s%3$s' : 'screen and %1$s',
                            $value['media'] ?: '',
                            $density > 1 ? " and (-webkit-min-device-pixel-ratio: $density)" : '',
                            $density > 1 ? " and (min-resolution: {$density}dppx)" : ''
                        ),
                        'src' => $src,
                    ];
                }
            }
        }

        return $mediaQueries;
    }

    private function getVideos(ModuleModel $model): array
    {
        if ($model->defineRoot) {
            $objPage = PageModel::findByPk($model->rootPage);
        } else {
            global $objPage;
        }

        if (null === $objPage) {
            return [];
        }

        $videos = $this->videohelper->findVideosForPage($objPage, (bool) $model->inheritPageImage);
        //print_r($videos);
        if (null === $videos) {
            return [];
        }

        if ($model->allPageImages) {
            return $videos;
        }

        if ($model->randomPageImage) {
            $index = random_int(0, \count($videos) - 1);
        } else {
            $index = $model->levelOffset;
        }

        if (!isset($videos[$index])) {
            return [];
        }

        return [$videos[$index]];
    }



}
