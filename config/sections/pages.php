<?php

use Kirby\Cms\Blueprint;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\A;

return [
    'mixins' => [
        'columns',
        'details',
        'empty',
        'headline',
        'help',
        'layout',
        'limits',
        'pagination',
        'parent',
        'search',
        'sort'
    ],
    'props' => [
        /**
         * Optional array of templates that should only be allowed to add
         * or `false` to completely disable page creation
         */
        'create' => function ($create = null) {
            return $create;
        },
        /**
         * Filters pages by their status. Available status settings: `draft`, `unlisted`, `listed`, `published`, `all`.
         */
        'status' => function (string $status = '') {
            if ($status === 'drafts') {
                $status = 'draft';
            }

            if (in_array($status, ['all', 'draft', 'published', 'listed', 'unlisted']) === false) {
                $status = 'all';
            }

            return $status;
        },
        /**
         * Filters the list by templates and sets template options when adding new pages to the section.
         */
        'templates' => function ($templates = null) {
            return A::wrap($templates ?? $this->template);
        }
    ],
    'computed' => [
        'parent' => function () {
            $parent = $this->parentModel();

            if (
                is_a($parent, 'Kirby\Cms\Site') === false &&
                is_a($parent, 'Kirby\Cms\Page') === false
            ) {
                throw new InvalidArgumentException('The parent is invalid. You must choose the site or a page as parent.');
            }

            return $parent;
        },
        'models' => function () {
            switch ($this->status) {
                case 'draft':
                    $pages = $this->parent->drafts();
                    break;
                case 'listed':
                    $pages = $this->parent->children()->listed();
                    break;
                case 'published':
                    $pages = $this->parent->children();
                    break;
                case 'unlisted':
                    $pages = $this->parent->children()->unlisted();
                    break;
                default:
                    $pages = $this->parent->childrenAndDrafts();
            }

            // filters pages that are protected and not in the templates list
            // internal `filter()` method used instead of foreach loop that previously included `unset()`
            // because `unset()` is updating the original data, `filter()` is just filtering
            // also it has been tested that there is no performance difference
            // even in 0.1 seconds on 100k virtual pages
            $pages = $pages->filter(function ($page) {
                // remove all protected pages
                if ($page->isReadable() === false) {
                    return false;
                }

                // filter by all set templates
                if ($this->templates && in_array($page->intendedTemplate()->name(), $this->templates) === false) {
                    return false;
                }

                return true;
            });

            // search
            if ($this->search === true && empty($this->query) === false) {
                $pages = $pages->search($this->query);
            }

            // sort
            if ($this->sortBy) {
                $pages = $pages->sort(...$pages::sortArgs($this->sortBy));
            }

            // flip
            if ($this->flip === true) {
                $pages = $pages->flip();
            }

            // pagination
            $pages = $pages->paginate([
                'page'   => $this->page,
                'limit'  => $this->limit,
                'method' => 'none' // the page is manually provided
            ]);

            return $pages;
        },
        'data' => function () {
            if ($this->layout === 'table') {
                return $this->rows();
            }

            $data = [];

            foreach ($this->models as $item) {
                $panel       = $item->panel();
                $permissions = $item->permissions();

                $row = [
                    'dragText'    => $panel->dragText(),
                    'id'          => $item->id(),
                    'image'       => $panel->image($this->image, $this->layout),
                    'info'        => $item->toSafeString($this->info ?? false),
                    'link'        => $panel->url(true),
                    'parent'      => $item->parentId(),
                    'permissions' => [
                        'sort'         => $permissions->can('sort'),
                        'changeSlug'   => $permissions->can('changeSlug'),
                        'changeStatus' => $permissions->can('changeStatus'),
                        'changeTitle'  => $permissions->can('changeTitle'),
                    ],
                    'status'      => $item->status(),
                    'template'    => $item->intendedTemplate()->name(),
                    'text'        => $item->toSafeString($this->text),
                ];

                $data[] = $row;
            }

            return $data;
        },
        'add' => function () {
            if ($this->create === false) {
                return false;
            }

            if (in_array($this->status, ['draft', 'all']) === false) {
                return false;
            }

            if ($this->isFull() === true) {
                return false;
            }

            return true;
        }
    ],
    'methods' => [
        'blueprints' => function () {
            $blueprints = [];
            $templates  = empty($this->create) === false ? A::wrap($this->create) : $this->templates;

            if (empty($templates) === true) {
                $templates = $this->kirby()->blueprints();
            }

            // convert every template to a usable option array
            // for the template select box
            foreach ($templates as $template) {
                try {
                    $props = Blueprint::load('pages/' . $template);

                    $blueprints[] = [
                        'name'  => basename($props['name']),
                        'title' => $props['title'],
                    ];
                } catch (Throwable $e) {
                    $blueprints[] = [
                        'name'  => basename($template),
                        'title' => ucfirst($template),
                    ];
                }
            }

            return $blueprints;
        }
    ],
    'toArray' => function () {
        return [
            'data'    => $this->data,
            'errors'  => $this->errors,
            'options' => [
                'add'      => $this->add,
                'columns'  => $this->columns,
                'empty'    => $this->empty,
                'headline' => $this->headline,
                'help'     => $this->help,
                'layout'   => $this->layout,
                'link'     => $this->link,
                'max'      => $this->max,
                'min'      => $this->min,
                'query'    => $this->query,
                'search'   => $this->search,
                'size'     => $this->size,
                'sortable' => $this->sortable
            ],
            'pagination' => $this->pagination,
        ];
    }
];
