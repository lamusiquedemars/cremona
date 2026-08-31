<?php

namespace App\Filament\Resources\EmailMailboxes;

use App\Filament\Resources\EmailMailboxes\Pages\CreateEmailMailbox;
use App\Filament\Resources\EmailMailboxes\Pages\ListEmailMailboxes;
use App\Models\EmailMailbox;
use App\Services\EmailMailboxConnectionTester;
use App\Services\EmailMailboxSynchronizer;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailMailboxResource extends Resource
{
    protected static ?string $model = EmailMailbox::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration de l’organisation';

    protected static ?string $navigationLabel = 'Boîtes email';

    protected static ?int $navigationSort = 110;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('address')->label('Adresse email')->email()->required(),
            TextInput::make('display_name')->label('Nom expéditeur')->maxLength(255),
            TextInput::make('imap_host')->label('Serveur IMAP')->required()->maxLength(255),
            TextInput::make('imap_port')->label('Port IMAP')->numeric()->default(993)->required(),
            TextInput::make('imap_username')->label('Identifiant IMAP')->required(),
            TextInput::make('imap_password')->label('Mot de passe IMAP')->password()->revealable()->required(),
            TextInput::make('smtp_host')->label('Serveur SMTP')->required()->maxLength(255),
            TextInput::make('smtp_port')->label('Port SMTP')->numeric()->default(465)->required(),
            TextInput::make('smtp_username')->label('Identifiant SMTP')->required(),
            TextInput::make('smtp_password')->label('Mot de passe SMTP')->password()->revealable()->required(),
            TextInput::make('inbox_folder')->label('Dossier reçu')->default('INBOX')->required(),
            TextInput::make('sent_folder')->label('Dossier envoyés')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('address')->label('Boîte')->searchable(),
            TextColumn::make('status')->label('État')->badge(),
            TextColumn::make('last_synced_at')->label('Dernière relève')->dateTime('d/m/Y H:i')->placeholder('Jamais'),
            TextColumn::make('last_error')->label('Dernier incident')->limit(80)->placeholder('Aucun'),
        ])->recordActions([
            Action::make('test_imap')
                ->label('Tester IMAP')
                ->action(function (EmailMailbox $record): void {
                    app(EmailMailboxConnectionTester::class)->testImap($record);
                    Notification::make()
                        ->title('Connexion IMAP réussie')
                        ->body('La boîte est accessible. Aucun message n’a été lu ni modifié.')
                        ->success()
                        ->send();
                }),
            Action::make('test_smtp')
                ->label('Tester SMTP')
                ->action(function (EmailMailbox $record): void {
                    app(EmailMailboxConnectionTester::class)->testSmtp($record);
                    Notification::make()
                        ->title('Connexion SMTP réussie')
                        ->body('L’envoi est authentifié. Aucun email n’a été envoyé.')
                        ->success()
                        ->send();
                }),
            Action::make('sync_now')
                ->label('Relever maintenant')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('Lit au plus 50 messages récents dans INBOX et, si configuré, dans Envoyés. Aucun message distant ne sera modifié.')
                ->action(function (EmailMailbox $record): void {
                    $result = app(EmailMailboxSynchronizer::class)->sync($record);
                    Notification::make()
                        ->title('Relève terminée')
                        ->body("{$result['imported']} message(s) importé(s), {$result['skipped']} déjà connu(s).")
                        ->success()
                        ->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListEmailMailboxes::route('/'), 'create' => CreateEmailMailbox::route('/create')];
    }
}
