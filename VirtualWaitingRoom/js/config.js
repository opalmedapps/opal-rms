// SPDX-FileCopyrightText: Copyright (C) 2024 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

(function() {
    'use strict';
  
    angular
      .module('vwr.config', [])
      .constant('CONFIG', {
        BRANDING_APP_LOGO_PATH: 'VirtualWaitingRoom/images/Opal_logo.png',
        BRANDING_RMS_LOGO_PATH: 'images/Opal_RMS_logo.png',
        BRANDING_MOBILE_APP_NAME: 'Opal Room Management System',
        BRANDING_SCREEN_DISPLAY_BACKGROUND_PATH: 'VirtualWaitingRoom/images/background.jpg',
        BRANDING_SCREEN_DISPLAY_BANNER_PATH: 'VirtualWaitingRoom/images/Banner_treatments.png',
        BRANDING_SUPPORT_EMAIL_ADDRESS: 'info@opalmedapps.com'
      });
  
  })();
