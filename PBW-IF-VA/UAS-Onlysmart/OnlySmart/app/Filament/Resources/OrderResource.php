<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Filament\Resources\OrderResource\RelationManagers\AddressRelationManager;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\TextArea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Str;
use Illuminate\Support\Number;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Forms\Set;
use Filament\Forms\Get;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Order Information')
                            ->schema([
                                Select::make('user_id')
                                    ->label('Customer')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('payment_method')
                                    ->options([
                                        'Dana' => 'Dana',
                                        'cod' => 'Cash on Delivery',
                                        'bri' => 'Bank Rakyat Indonesia',
                                        'bni' => 'Bank Negara Indonesia',
                                        'bca' => 'Bank Central Asia',
                                        'bjb' => 'Bank Jabar Asia',
                                    ])
                                    ->required(),

                                Select::make('payment_status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'paid' => 'Paid',
                                        'failed' => 'Failed',
                                    ])
                                    ->default('pending')
                                    ->required(),

                                ToggleButtons::make('status')
                                    ->inline()
                                    ->default('new')
                                    ->options([
                                        'new' => 'New',
                                        'processing' => 'Processing',
                                        'shipped' => 'Shipped',
                                        'delivered' => 'Delivered',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->colors([
                                        'new' => 'info',
                                        'processing' => 'warning',
                                        'shipped' => 'success',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger',
                                    ])
                                    ->icons([
                                        'new' => 'heroicon-m-sparkles',
                                        'processing' => 'heroicon-m-arrow-path',
                                        'shipped' => 'heroicon-m-truck',
                                        'delivered' => 'heroicon-m-check-badge',
                                        'cancelled' => 'heroicon-m-x-circle',
                                    ]),

                                Select::make('currency')
                                    ->options([
                                        'idr' => 'IDR',
                                        'usd' => 'USD',
                                        'eur' => 'EUR',
                                    ])
                                    ->default('idr')
                                    ->required(),

                                Select::make('shipping_method')
                                    ->options([
                                        'reguler' => 'Reguler',
                                        'express' => 'Express',
                                    ])
                                    ->default('reguler')
                                    ->required(),

                                TextArea::make('notes')
                                    ->rows(5),
                            ])
                            ->columns(2), 

                        Section::make('Order Items')
                            ->schema([
                                Repeater::make('items')
                                    ->relationship()
                                    ->schema([
                                        Select::make('product_id')
                                            ->relationship('product', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->distinct()
                                            ->reactive()
                                            ->afterStateUpdated(fn ($state, Set $set) => $set('unit_amount', Product::find($state)->price ?? 0))
                                            ->afterStateUpdated(fn ($state, Set $set) => $set('total_amount', Product::find($state)->price ?? 0))
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->required()
                                            ->default(1)
                                            ->minValue(1)
                                            ->reactive()
                                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => $set('total_amount', $state * $get('unit_amount'))),

                                        TextInput::make('unit_amount')
                                            ->numeric()
                                            ->required()
                                            ->disabled()
                                            ->dehydrated(),

                                        TextInput::make('total_amount')
                                            ->numeric()
                                            ->dehydrated()
                                            ->required()
                                    ]),

                                Placeholder::make('grand_total_placeholder')
                                    ->label('Grand Total')
                                    ->content(function (Get $get, Set $set) {
                                        $total = 0;
                                        if (!$repeaters = $get('items')) {
                                            return $total;
                                        }
                                        foreach ($repeaters as $key => $repeater) {
                                            $total += $get("items.{$key}.total_amount");
                                        }
                                        $set('grand_total', $total);
                                        return Number::currency($total, 'IDR');
                                    }),

                                Hidden::make('grand_total')
                                    ->default(0)
                            ])
                            ->columns(1) 
                    ])
                    ->columnSpanFull() 
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 
                TextColumn::make('user.name')
                ->sortable()
                ->label('Customer')
                ->searchable(),

                TextColumn::make('grand_total')
                ->sortable()
                ->numeric()
                ->money('IDR'),

                TextColumn::make('payment_method')
                ->sortable()
                ->searchable(),

                TextColumn::make('payment_status')
                ->sortable()
                ->searchable(),

                TextColumn::make('currency')
                ->sortable()
                ->searchable(),

                TextColumn::make('shipping_method')
                ->sortable()
                ->searchable(),

                SelectColumn::make('status')
                ->options([
                    'new' => 'New',
                    'processing' => 'Processing',
                    'shipped' => 'Shipped',
                    'delivered' => 'Delivered',
                    'cancelled' => 'Cancelled',
                ])
                ->sortable()
                ->searchable(),

                TextColumn::make('created_at')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('updated_at')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            ])
            
            

            ->filters([
                // Tambahkan filter sesuai kebutuhan
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    ])
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //

            AddressRelationManager::class
        ];
    }

    public static function getNavigationBadge(): ?string {
        return static::getModel()::count();
    }
    public static function getNavigationBadgeColor(): string|array|null {
        return static::getModel()::count() > 10 ? 'success': 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}