<?php

namespace App\Filament\Resources\PermissionResource\Pages;  



use App\Filament\Resources\PermissionResource;      
use Filament\Resources\Pages\ListRecords;   
USE Filament\Actions;   



class ListPermissions extends ListRecords
{
  protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}