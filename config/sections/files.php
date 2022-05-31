<?php

use Kirby\Cms\File;
use Kirby\Toolkit\I18n;

return [
    'mixins' => [
        'empty',
        'headline',
        'help',
        'layout',
        'min',
        'max',
        'pagination',
        'parent',
        'search'
    ],
    'props' => [
        'columns' => function (array $columns = null) {
            return $columns ?? [];
        },
        /**
         * Enables/disables reverse sorting
         */
        'flip' => function (bool $flip = false) {
            return $flip;
        },
        /**
         * Image options to control the source and look of file previews
         */
        'image' => function ($image = null) {
            return $image ?? [];
        },
        /**
         * Optional info text setup. Info text is shown on the right (lists, cardlets) or below (cards) the filename.
         */
        'info' => function ($info = null) {
            return I18n::translate($info, $info);
        },
        /**
         * The size option controls the size of cards. By default cards are auto-sized and the cards grid will always fill the full width. With a size you can disable auto-sizing. Available sizes: `tiny`, `small`, `medium`, `large`, `huge`
         */
        'size' => function (string $size = 'auto') {
            return $size;
        },
        /**
         * Enables/disables manual sorting
         */
        'sortable' => function (bool $sortable = true) {
            return $sortable;
        },
        /**
         * Overwrites manual sorting and sorts by the given field and sorting direction (i.e. `filename desc`)
         */
        'sortBy' => function (string $sortBy = null) {
            return $sortBy;
        },
        /**
         * Filters all files by template and also sets the template, which will be used for all uploads
         */
        'template' => function (string $template = null) {
            return $template;
        },
        /**
         * Setup for the main text in the list or cards. By default this will display the filename.
         */
        'text' => function ($text = '{{ file.filename }}') {
            return I18n::translate($text, $text);
        }
    ],
    'computed' => [
        'accept' => function () {
            if ($this->template) {
                $file = new File([
                    'filename' => 'tmp',
                    'parent'   => $this->model(),
                    'template' => $this->template
                ]);

                return $file->blueprint()->acceptMime();
            }

            return null;
        },
        'columns' => function () {
            $columns = [];

            if ($this->image !== false) {
                $columns['image'] = [
                    'label' => ' ',
                    'type'  => 'image',
                    'width' => 'var(--table-row-height)'
                ];
            }

            $columns['filename'] = [
                'label' => 'Filename',
                'type'  => 'url'
            ];

            if ($this->info) {
                $columns['info'] = [
                    'label' => 'Info',
                    'type'  => 'text',
                ];
            }

            foreach ($this->columns as $columnName => $column) {
                $column['id'] = $columnName;
                $columns[$columnName . 'Cell'] = $column;
            }

            return $columns;
        },
        'parent' => function () {
            return $this->parentModel();
        },
        'files' => function () {
            $files = $this->parent->files()->template($this->template);

            // filter out all protected files
            $files = $files->filter('isReadable', true);

            // search
            if ($this->search === true && empty($this->query) === false) {
                $files = $files->search($this->query);
            }

            // sort
            if ($this->sortBy) {
                $files = $files->sort(...$files::sortArgs($this->sortBy));
            } else {
                $files = $files->sorted();
            }

            // flip
            if ($this->flip === true) {
                $files = $files->flip();
            }

            // apply the default pagination
            $files = $files->paginate([
                'page'   => $this->page,
                'limit'  => $this->limit,
                'method' => 'none' // the page is manually provided
            ]);

            return $files;
        },
        'data' => function () {
            if ($this->layout === 'table') {
                return $this->rows();
            }

            $data = [];

            // the drag text needs to be absolute when the files come from
            // a different parent model
            $dragTextAbsolute = $this->model->is($this->parent) === false;

            foreach ($this->files as $file) {
                $panel = $file->panel();

                $data[] = [
                    'dragText'  => $panel->dragText('auto', $dragTextAbsolute),
                    'extension' => $file->extension(),
                    'filename'  => $file->filename(),
                    'id'        => $file->id(),
                    'image'     => $panel->image($this->image, $this->layout),
                    'info'      => $file->toSafeString($this->info ?? false),
                    'link'      => $panel->url(true),
                    'mime'      => $file->mime(),
                    'parent'    => $file->parent()->panel()->path(),
                    'template'  => $file->template(),
                    'text'      => $file->toSafeString($this->text),
                    'url'       => $file->url(),
                ];
            }

            return $data;
        },
        'total' => function () {
            return $this->files->pagination()->total();
        },
        'errors' => function () {
            $errors = [];

            if ($this->validateMax() === false) {
                $errors['max'] = I18n::template('error.section.files.max.' . I18n::form($this->max), [
                    'max'     => $this->max,
                    'section' => $this->headline
                ]);
            }

            if ($this->validateMin() === false) {
                $errors['min'] = I18n::template('error.section.files.min.' . I18n::form($this->min), [
                    'min'     => $this->min,
                    'section' => $this->headline
                ]);
            }

            if (empty($errors) === true) {
                return [];
            }

            return [
                $this->name => [
                    'label'   => $this->headline,
                    'message' => $errors,
                ]
            ];
        },
        'link' => function () {
            $modelLink  = $this->model->panel()->url(true);
            $parentLink = $this->parent->panel()->url(true);

            if ($modelLink !== $parentLink) {
                return $parentLink;
            }
        },
        'pagination' => function () {
            return $this->pagination();
        },
        'sortable' => function () {
            if ($this->sortable === false) {
                return false;
            }

            if (empty($this->query) === false) {
                return false;
            }

            if ($this->sortBy !== null) {
                return false;
            }

            if ($this->flip === true) {
                return false;
            }

            return true;
        },
        'upload' => function () {
            if ($this->isFull() === true) {
                return false;
            }

            // count all uploaded files
            $total = count($this->data);
            $max   = $this->max ? $this->max - $total : null;

            if ($this->max && $total === $this->max - 1) {
                $multiple = false;
            } else {
                $multiple = true;
            }

            $template = $this->template === 'default' ? null : $this->template;

            return [
                'accept'     => $this->accept,
                'multiple'   => $multiple,
                'max'        => $max,
                'api'        => $this->parent->apiUrl(true) . '/files',
                'attributes' => array_filter([
                    'sort'     => $this->sortable === true ? $total + 1 : null,
                    'template' => $template
                ])
            ];
        }
    ],
    'methods' => [
        'rows' => function () {

            $rows = [];

            foreach ($this->files as $item) {

                $panel = $item->panel();
                $row   = [];

                $row['filename'] = [
                    'text' => $item->toSafeString($this->text),
                    'href' => $panel->url(true)
                ];

                $row['id']          = $item->id();
                $row['image']       = $panel->image($this->image, 'list');
                $row['info']        = $item->toSafeString($this->info ?? false);
                $row['permissions'] = $item->permissions();
                $row['link']        = $panel->url(true);

                // custom columns
                foreach ($this->columns as $columnName => $column) {
                    // don't overwrite essential columns
                    if (isset($row[$columnName]) === true) {
                        continue;
                    }

                    if (empty($column['value']) === false) {
                        $value = $item->toSafeString($column['value']);
                    } else {
                        $value = $item->content()->get($column['id'] ?? $columnName)->value();
                    }

                    $row[$columnName] = $value;
                }

                $rows[] = $row;
            }

            return $rows;

        }
    ],
    'toArray' => function () {
        return [
            'data'    => $this->data,
            'errors'  => $this->errors,
            'options' => [
                'accept'   => $this->accept,
                'apiUrl'   => $this->parent->apiUrl(true),
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
                'sortable' => $this->sortable,
                'upload'   => $this->upload
            ],
            'pagination' => $this->pagination
        ];
    }
];
