<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Models\Review;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        // Two different forms behind one class: customer-submitted reviews
        // are moderated (every field but status/rating/verified is
        // disabled, matching how ReviewsTable's approve/reject actions
        // already treat them), but an admin authoring a review from
        // scratch (ReviewResource::canCreate()) needs every field editable.
        // $schema->getOperation() is the one signal Filament gives a shared
        // form class to tell which page it's rendering for.
        return $schema->getOperation() === 'create'
            ? self::createSchema($schema)
            : self::editSchema($schema);
    }

    private static function createSchema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Review')
                    ->columns(2)
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'title')
                            ->searchable()
                            ->required(),
                        TextInput::make('customer_name')
                            ->label('Customer Name')
                            ->required(),
                        DatePicker::make('review_date')
                            ->label('Review Date')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d-M-Y'),
                        Select::make('rating')
                            ->label('Star Rating')
                            ->options([
                                5 => '★★★★★ (5)',
                                4 => '★★★★☆ (4)',
                                3 => '★★★☆☆ (3)',
                                2 => '★★☆☆☆ (2)',
                                1 => '★☆☆☆☆ (1)',
                            ])
                            ->default(5)
                            ->required(),
                        TextInput::make('title')
                            ->label('Review Title')
                            ->maxLength(150)
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->label('Review Message')
                            ->rows(4)
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('is_verified_purchase')
                            ->label('Verified Purchase')
                            ->default(true),
                        // Maps to status (approved/pending) in
                        // CreateReview::mutateFormDataBeforeCreate() — not a
                        // real column, this form's own vocabulary for it.
                        Toggle::make('is_visible')
                            ->label('Visible')
                            ->default(true)
                            ->dehydrated(false),
                    ]),

                Section::make('Product Image')
                    ->description('Optional — shown alongside the review if provided.')
                    ->schema([
                        FileUpload::make('photo')
                            ->label('')
                            ->image()
                            ->imageEditor()
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->disk('original_images')
                            // Not a model attribute — CreateReview attaches
                            // this to the photos media collection itself
                            // after the record exists, since Media Library
                            // needs a persisted model to attach to.
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function editSchema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Review')
                    ->columns(2)
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'title')
                            ->disabled(),
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                        TextInput::make('customer_name')->disabled(),
                        TextInput::make('customer_email')->disabled(),
                        TextInput::make('rating')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5)
                            ->required(),
                        Toggle::make('is_verified_purchase')
                            ->label('Verified purchase'),
                        TextInput::make('title')
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Photos')
                    ->visible(fn (?Review $record) => $record?->hasMedia('photos'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->collection('photos')
                            ->conversion('thumb')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
