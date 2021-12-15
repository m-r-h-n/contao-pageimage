<?php

declare(strict_types=1);

namespace Terminal42\PageimageBundle;

use Contao\File;
use Contao\FilesModel;
use Contao\Frontend;
use Contao\PageModel;
use Contao\StringUtil;

class PagevideoHelper
{
    /**
     * @var array
     */

    protected static $videosCache = [];



    public function getOneVideoByPageAndIndex(PageModel $page, ?int $index = 0, bool $inherit = true): ?array
    {
        $videos = $this->findVideosForPage($page, $inherit);

        if (null === $videos) {
            return null;
        }

        // Random video
        if (null === $index) {
            $index = random_int(0, \count($videos) - 1);
        }

        if (!isset($videos[$index])) {
            return null;
        }

        return $videos[$index];
    }




    public function findForPage(PageModel $page, bool $inherit = true): ?array
    {
        if (!isset(static::$videosCache[$page->id])) {
            static::$videosCache[$page->id] = false;

            $videos = $this->parsePageVideos($page);
           // print_r($videos);
            if (!empty($videos)) {
                static::$videosCache[$page->id] = [
                    'videos' => $videos,
                    'inherited' => false,
                ];
            } else {
                $page->loadDetails();
                $parentPages = PageModel::findMultipleByIds(array_reverse($page->trail));

                if (null !== $parentPages) {
                    foreach ($parentPages as $parentPage) {
                        $videos = $this->parsePageVideos($parentPage);
                       // print_r($videos);
                        if (!empty($videos)) {
                            static::$videosCache[$page->id] = [
                                'videos' => $videos,
                                'inherited' => true,
                            ];

                            break;
                        }
                    }
                }
            }
        }

        if (false === static::$videosCache[$page->id] || (!$inherit && static::$videosCache[$page->id]['inherited'])) {
            return null;
        }
        //print_r(static::$videosCache[$page->id]['videos']);
        return static::$videosCache[$page->id]['videos'];
    }




    private function parsePageVideos(PageModel $page): array
    {
        if (empty($page->pageVideo)) {
            return [];
        }

        $videos = [];
        $files = FilesModel::findMultipleByUuids(StringUtil::deserialize($page->pageVideo, true));

        if (null !== $files) {
            foreach ($files as $file) {
                $objFile = new File($file->path);

                $video = $file->row();

                if ($page->pageImageOverwriteMeta) {
                    $video['alt'] = $page->pageImageAlt;
                    $video['linkTitle'] = $page->pageImageTitle;
                    $video['href'] = $page->pageImageUrl;
                }

                $videos[] = $video;
            }

            $videos = $this->sortVideos($videos, $page);

        }
        return $videos;

    }





    private function sortVideos(array $videos, PageModel $pageModel): array
    {
        $order = StringUtil::deserialize($pageModel->pageVideoOrder);

        if (empty($order) || !\is_array($order)) {
            return $videos;
        }

        // Remove all values
        $order = array_map(static function (): void {}, array_flip($order));

        // Move the matching elements to their position in $order
        foreach ($videos as $k => $v) {
            if (\array_key_exists($v['uuid'], $order)) {
                $order[$v['uuid']] = $v;
                unset($videos[$k]);
            }
        }

        // Append the left-over images at the end
        if (!empty($videos)) {
            $order = array_merge($order, array_values($videos));
        }

        // Remove empty (unreplaced) entries
        return array_values(array_filter($order));
    }




}
