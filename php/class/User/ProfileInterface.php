<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\User;

use Orms\DataAccess\ProfileAccess;

class ProfileInterface
{
    /**
     *
     * @return null|array{
     *  profileSer: int,
     *  profileId: string,
     *  category: string,
     *  specialityGroupId: int,
     * }
     */
    public static function getProfile(string $profileId): ?array
    {
        return ProfileAccess::getProfile($profileId);
    }

    /**
     *
     * @return list<array{
     *  columnName: string,
     *  displayName: string,
     *  glyphicon: string,
     *  position: int,
     * }>
     */
    public static function getProfileColumns(string $profileId): array
    {
        return ProfileAccess::getProfileColumns($profileId);
    }

    /**
     *
     * @return list<array{
     *  name: string,
     *  type: string
     * }>
     */
    public static function getProfileOptions(string $profileId): array
    {
        return ProfileAccess::getProfileOptions($profileId);
    }

    /**
     *
     * @return list<array{
     *  ProfileSer: int,
     *  ProfileId: string,
     *  Category: string,
     * }>
     */
    public static function getProfileList(?string $category,?int $specialityGroupId): array
    {
        return ProfileAccess::getProfileList($category,$specialityGroupId);
    }

    /**
     *
     * @return list<array{
     *  ColumnName: string,
     *  DisplayName: string,
     *  Glyphicon: string,
     *  Description: string,
     * }>
     */
    public static function getColumnDefinitions(): array
    {
        return ProfileAccess::getColumnDefinitions();
    }

    public static function createProfile(string $profileId,int $specialityGroupId): int
    {
        return ProfileAccess::createProfile($profileId,$specialityGroupId);
    }

    /**
     *
     * @param list<array{Name: string, Type: string}> $options
     * @param list<string> $columns
     */
    public static function updateProfile(int $profileSer,string $profileId,string $category,int $specialityGroupId,array $options,array $columns): void
    {
        ProfileAccess::updateProfile($profileSer,$profileId,$category,$specialityGroupId,$options,$columns);
    }

    public static function deleteProfile(string $profileId): void
    {
        ProfileAccess::deleteProfile($profileId);
    }
}
