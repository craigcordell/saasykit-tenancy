<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BlogPostResource\Pages;
use App\Models\BlogPost;
use CodeIsAwesome\FilamentTinyEditor\TinyEditor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('Blog');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->label(__('Title'))
                        ->maxLength(1000),
                    Forms\Components\Textarea::make('description')
                        ->maxLength(1000)
                        ->helperText(__('A short description of the post (will be used in meta tags).'))
                        ->label('Description')
                        ->rows(2),
                    TinyEditor::make('body')
                        ->columns(10)
                        ->required()
                        ->label(__('Content'))
                        ->toolbarSticky(true)
                        ->setRelativeUrls(false)
                        ->fileAttachmentsDirectory('blog-images')
                        ->columnSpanFull(),
                ])->columnSpan(2),
                Forms\Components\Section::make([
                    Forms\Components\TextInput::make('slug')
                        ->label(__('Slug'))
                        ->helperText(__('Will be used in the URL of the post. Leave empty to generate slug automatically from title.'))
                        ->dehydrateStateUsing(function ($state, \Filament\Forms\Get $get) {
                            if (empty($state)) {
                                $title = $get('title');

                                return Str::slug($title);
                            }

                            return Str::slug($state);
                        })
                        ->maxLength(255),
                    Forms\Components\Select::make('blog_post_category_id')
                        ->relationship('blogPostCategory', 'name'),
                    Forms\Components\Select::make('author_id')
                        ->label(__('Author'))
                        ->default(auth()->user()->id)
                        ->required()
                        ->options(
                            \App\Models\User::admin()->get()->sortBy('name')
                                ->mapWithKeys(function ($user) {
                                    return [$user->id => $user->getPublicName()];
                                })
                                ->toArray()
                        ),
                    Forms\Components\SpatieMediaLibraryFileUpload::make('image')
                        ->collection('blog-images')
                        ->label(__('Images'))
                        ->acceptedFileTypes(['image/webp', 'image/jpeg', 'image/png']),
                    Forms\Components\Toggle::make('is_published')
                        ->label(__('Is Published'))
                        ->required(),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label(__('Published At'))
                        ->required(function ($state, \Filament\Forms\Get $get) {
                            return $get('is_published');
                        }),
                ])->columnSpan(1),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('author_id')
                    ->label(__('Author'))
                    ->formatStateUsing(function ($state, $record) {
                        return $record->author->getPublicName();
                    })
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->label(__('Published'))
                    ->boolean(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'author',
                'user',
            ]))
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Blog Posts');
    }
}
