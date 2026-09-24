<?php

namespace App\Filament\Resources\Vendors\Tables;

use App\Models\Vendor;
use App\Services\Otp\OtpManager;
use App\Services\Vendors\PanelAccessService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginationMode(PaginationMode::Simple)
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->withCount([
                'invitations',
                'invitations as accepted_count' => fn (Builder $q) => $q->where('response_status', 'accepted'),
            ]))
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (Vendor $record) => $record->company_name),
                TextColumn::make('mobile')
                    ->searchable()
                    ->icon(fn (Vendor $record) => $record->mobile_verified_at ? Heroicon::OutlinedCheckBadge : null)
                    ->iconColor('success')
                    ->tooltip(fn (Vendor $record) => $record->mobile_verified_at ? 'Mobile verified' : 'Mobile not verified'),
                TextColumn::make('login_state')
                    ->label('Login')
                    ->badge()
                    ->state(fn (Vendor $record) => match (true) {
                        blank($record->email) => 'No email',
                        $record->user === null => 'Not invited',
                        filled($record->user->password) => 'Active',
                        default => 'Invited',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Active' => 'success',
                        'Invited' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('bids')
                    ->label('Bids')
                    ->state(fn (Vendor $record) => "{$record->accepted_count} / {$record->invitations_count}")
                    ->tooltip('Accepted / invited')
                    ->alignCenter(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('email')->searchable()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('whatsapp_number')->label('WhatsApp')->searchable()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime('d M Y, h:i A')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
                TernaryFilter::make('verified')
                    ->label('Mobile verified')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('mobile_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('mobile_verified_at'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ViewAction::make()->label('Performance'),
                    self::verifyOtpAction(),
                    self::resendSetupLinkAction(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    /**
     * Issues a fresh 48-hour setup link. Also the recovery path when the first
     * mail never arrived or the link expired — old tokens stop working as soon
     * as a new one is issued.
     */
    public static function resendSetupLinkAction(): Action
    {
        return Action::make('resend_setup_link')
            ->label('Resend setup link')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('info')
            ->visible(fn (Vendor $record) => filled($record->email))
            ->requiresConfirmation()
            ->modalHeading('Resend password setup link')
            ->modalDescription(fn (Vendor $record) => "A new link will be sent to {$record->email}"
                .(filled($record->whatsapp_number) ? " and WhatsApp {$record->whatsapp_number}" : '')
                .'. Any previous link stops working.')
            ->action(function (Vendor $record) {
                $service = app(PanelAccessService::class);

                try {
                    if ($service->grant($record, isResend: true)) {
                        Notification::make()->title("Setup link sent to {$record->email}")->success()->send();

                        return;
                    }
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('The setup email was not delivered')
                    ->body(new \Illuminate\Support\HtmlString(nl2br(e($service->failureDetails()))))
                    ->danger()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * One button, one step: clicking "Verify mobile" fires the OTP straight
     * away and opens the modal already on the code field — no separate
     * "send" button for the admin to notice and press first. A quiet
     * "Resend code" footer link covers the code expiring or never arriving,
     * without competing with the single primary action.
     */
    public static function verifyOtpAction(): Action
    {
        return Action::make('verify_otp')
            ->label('Verify mobile')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('success')
            ->visible(fn (Vendor $record) => ! $record->mobile_verified_at)
            // Runs when the modal mounts, so the single "Verify mobile"
            // click both fires the OTP and opens the modal straight on the
            // code field — no separate "send" step to show. Must still fill
            // the (empty) form schema itself, same as Filament's own default
            // mountUsing() does, or the modal's form never initialises.
            ->mountUsing(function (Schema $schema, Vendor $record) {
                $schema->fill();
                app(OtpManager::class)->issue($record->mobile);
            })
            ->modalHeading(fn (Vendor $record) => "Verify {$record->mobile}")
            ->modalDescription('Enter the 6-digit code just sent to the vendor.')
            ->modalSubmitActionLabel('Verify')
            ->extraModalFooterActions([
                Action::make('resend_otp')
                    ->label('Resend code')
                    ->link()
                    ->color('gray')
                    ->action(function (Vendor $record) {
                        app(OtpManager::class)->issue($record->mobile);

                        Notification::make()->title('Code resent.')->success()->send();
                    }),
            ])
            ->schema([
                TextInput::make('code')
                    ->label('6-digit code')
                    ->required()
                    ->numeric()
                    ->length(6)
                    ->autofocus(),
            ])
            ->action(function (array $data, Action $action, Vendor $record) {
                if (! app(OtpManager::class)->verify($record->mobile, (string) $data['code'])) {
                    Notification::make()->title('Invalid or expired code.')->danger()->send();

                    // Keep the modal open on a wrong code instead of closing
                    // it — closing would force the admin to click "Verify
                    // mobile" again just to retry, which re-issues a whole
                    // new OTP for what might just be a typo.
                    $action->halt();
                }

                $record->update(['mobile_verified_at' => now()]);

                Notification::make()->title('Verified.')->success()->send();
            });
    }
}
