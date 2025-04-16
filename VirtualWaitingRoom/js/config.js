// SPDX-FileCopyrightText: Copyright (C) 2024 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

(function() {
    'use strict';
  
    angular
      .module('vwr.config', [])
      .constant('CONFIG', {
        BRANDING_APP_LOGO_PATH: '{your_app_logo_path}',
        BRANDING_RMS_LOGO_PATH: 'images/BRANDING_your_logo_here.png',
        BRANDING_MOBILE_APP_NAME: '{APP_NAME}',
        BRANDING_SCREEN_DISPLAY_BACKGROUND_PATH: 'VirtualWaitingRoom/images/BRANDING_customize_watermark_background.png',
        BRANDING_SCREEN_DISPLAY_BANNER_PATH: 'VirtualWaitingRoom/images/BRANDING_banner_your_product_name.png',
        BRANDING_SUPPORT_EMAIL_ADDRESS: '{BRANDING_SUPPORT_EMAIL_ADDRESS}'
      });
  
  })();