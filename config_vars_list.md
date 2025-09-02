

> TODO: Format this document better and add some more descriptions

- [PHP](#php)
- [Apache](#apache)
- [www-data user and group ID](#www-data-user-and-group-id)
- [Run custom or upgrade SQL Scripts at boot](#run-custom-or-upgrade-sql-scripts-at-boot)
- [Fix REDCap source files/directory permissions](#fix-redcap-source-filesdirectory-permissions)
- [REDCap database connection environment variable](#redcap-database-connection-environment-variable)
  - [REDCap Data Transfer Services](#redcap-data-transfer-services)
- [User provisioning](#user-provisioning)
- [REDCap installation](#redcap-installation)
  - [Option 1 - automated installation](#option-1---automated-installation)
  - [Option 2 - Installation with bring-your-own SQL install Script](#option-2---installation-with-bring-your-own-sql-install-script)
- [REDCap upgrade](#redcap-upgrade)
- [REDCap Basic Admin tasks](#redcap-basic-admin-tasks)
  - [suspend site\_admin](#suspend-site_admin)
- [MSMTP](#msmtp)
- [Cron](#cron)
- [REDCap Application Config vars](#redcap-application-config-vars)
  - [APPLY\_RCCONF\_VARIABLES](#apply_rcconf_variables)
  - [Possible config variables](#possible-config-variables)
    - [RCCONF\_mtb\_enabled](#rcconf_mtb_enabled)
    - [RCCONF\_cache\_files\_filesystem\_path](#rcconf_cache_files_filesystem_path)
    - [RCCONF\_allow\_auto\_variable\_naming](#rcconf_allow_auto_variable_naming)
    - [RCCONF\_mailgun\_api\_endpoint](#rcconf_mailgun_api_endpoint)
    - [RCCONF\_openid\_connect\_additional\_scope](#rcconf_openid_connect_additional_scope)
    - [RCCONF\_read\_replica\_enable](#rcconf_read_replica_enable)
    - [RCCONF\_azure\_comm\_api\_endpoint](#rcconf_azure_comm_api_endpoint)
    - [RCCONF\_azure\_comm\_api\_key](#rcconf_azure_comm_api_key)
    - [RCCONF\_fhir\_custom\_auth\_params](#rcconf_fhir_custom_auth_params)
    - [RCCONF\_fhir\_custom\_mapping\_file\_id](#rcconf_fhir_custom_mapping_file_id)
    - [RCCONF\_oauth2\_azure\_ad\_tenant](#rcconf_oauth2_azure_ad_tenant)
    - [RCCONF\_display\_inline\_pdf\_in\_pdf](#rcconf_display_inline_pdf_in_pdf)
    - [RCCONF\_mosio\_enabled\_global](#rcconf_mosio_enabled_global)
    - [RCCONF\_mosio\_display\_info\_project\_setup](#rcconf_mosio_display_info_project_setup)
    - [RCCONF\_mosio\_enabled\_by\_super\_users\_only](#rcconf_mosio_enabled_by_super_users_only)
    - [RCCONF\_rich\_text\_attachment\_embed\_enabled](#rcconf_rich_text_attachment_embed_enabled)
    - [RCCONF\_oauth2\_azure\_ad\_name](#rcconf_oauth2_azure_ad_name)
    - [RCCONF\_admin\_email\_external\_user\_creation](#rcconf_admin_email_external_user_creation)
    - [RCCONF\_user\_welcome\_email\_external\_user\_creation](#rcconf_user_welcome_email_external_user_creation)
    - [RCCONF\_openid\_connect\_response\_type](#rcconf_openid_connect_response_type)
    - [RCCONF\_restricted\_upload\_file\_types](#rcconf_restricted_upload_file_types)
    - [RCCONF\_file\_repository\_allow\_public\_link](#rcconf_file_repository_allow_public_link)
    - [RCCONF\_file\_repository\_total\_size](#rcconf_file_repository_total_size)
    - [RCCONF\_contact\_admin\_button\_url](#rcconf_contact_admin_button_url)
    - [RCCONF\_rich\_text\_image\_embed\_enabled](#rcconf_rich_text_image_embed_enabled)
    - [RCCONF\_two\_factor\_auth\_enforce\_table\_users\_only](#rcconf_two_factor_auth_enforce_table_users_only)
    - [RCCONF\_openid\_connect\_username\_attribute](#rcconf_openid_connect_username_attribute)
    - [RCCONF\_calendar\_feed\_enabled\_global](#rcconf_calendar_feed_enabled_global)
    - [RCCONF\_sendgrid\_enabled\_global](#rcconf_sendgrid_enabled_global)
    - [RCCONF\_sendgrid\_enabled\_by\_super\_users\_only](#rcconf_sendgrid_enabled_by_super_users_only)
    - [RCCONF\_sendgrid\_display\_info\_project\_setup](#rcconf_sendgrid_display_info_project_setup)
    - [RCCONF\_two\_factor\_auth\_esign\_pin](#rcconf_two_factor_auth_esign_pin)
    - [RCCONF\_esignature\_enabled\_global](#rcconf_esignature_enabled_global)
    - [RCCONF\_openid\_connect\_name](#rcconf_openid_connect_name)
    - [RCCONF\_openid\_connect\_primary\_admin](#rcconf_openid_connect_primary_admin)
    - [RCCONF\_openid\_connect\_secondary\_admin](#rcconf_openid_connect_secondary_admin)
    - [RCCONF\_openid\_connect\_provider\_url](#rcconf_openid_connect_provider_url)
    - [RCCONF\_openid\_connect\_metadata\_url](#rcconf_openid_connect_metadata_url)
    - [RCCONF\_openid\_connect\_client\_id](#rcconf_openid_connect_client_id)
    - [RCCONF\_openid\_connect\_client\_secret](#rcconf_openid_connect_client_secret)
    - [RCCONF\_database\_query\_tool\_enabled](#rcconf_database_query_tool_enabled)
    - [RCCONF\_amazon\_s3\_endpoint\_url](#rcconf_amazon_s3_endpoint_url)
    - [RCCONF\_new\_form\_default\_prod\_user\_access](#rcconf_new_form_default_prod_user_access)
    - [RCCONF\_file\_upload\_vault\_filesystem\_authtype](#rcconf_file_upload_vault_filesystem_authtype)
    - [RCCONF\_pdf\_econsent\_filesystem\_authtype](#rcconf_pdf_econsent_filesystem_authtype)
    - [RCCONF\_record\_locking\_pdf\_vault\_filesystem\_authtype](#rcconf_record_locking_pdf_vault_filesystem_authtype)
    - [RCCONF\_config\_settings\_key](#rcconf_config_settings_key)
    - [RCCONF\_oauth2\_azure\_ad\_username\_attribute](#rcconf_oauth2_azure_ad_username_attribute)
    - [RCCONF\_oauth2\_azure\_ad\_endpoint\_version](#rcconf_oauth2_azure_ad_endpoint_version)
    - [RCCONF\_pdf\_econsent\_filesystem\_container](#rcconf_pdf_econsent_filesystem_container)
    - [RCCONF\_record\_locking\_pdf\_vault\_filesystem\_container](#rcconf_record_locking_pdf_vault_filesystem_container)
    - [RCCONF\_file\_upload\_vault\_filesystem\_container](#rcconf_file_upload_vault_filesystem_container)
    - [RCCONF\_google\_cloud\_storage\_api\_bucket\_name](#rcconf_google_cloud_storage_api_bucket_name)
    - [RCCONF\_google\_cloud\_storage\_api\_project\_id](#rcconf_google_cloud_storage_api_project_id)
    - [RCCONF\_google\_cloud\_storage\_api\_service\_account](#rcconf_google_cloud_storage_api_service_account)
    - [RCCONF\_google\_cloud\_storage\_api\_use\_project\_subfolder](#rcconf_google_cloud_storage_api_use_project_subfolder)
    - [RCCONF\_override\_system\_bundle\_ca](#rcconf_override_system_bundle_ca)
    - [RCCONF\_fhir\_break\_the\_glass\_department\_type](#rcconf_fhir_break_the_glass_department_type)
    - [RCCONF\_fhir\_break\_the\_glass\_patient\_type](#rcconf_fhir_break_the_glass_patient_type)
    - [RCCONF\_email\_logging\_enable\_global](#rcconf_email_logging_enable_global)
    - [RCCONF\_email\_logging\_install\_time](#rcconf_email_logging_install_time)
    - [RCCONF\_protected\_email\_mode\_global](#rcconf_protected_email_mode_global)
    - [RCCONF\_password\_length](#rcconf_password_length)
    - [RCCONF\_password\_complexity](#rcconf_password_complexity)
    - [RCCONF\_reports\_allow\_public](#rcconf_reports_allow_public)
    - [RCCONF\_mailgun\_api\_key](#rcconf_mailgun_api_key)
    - [RCCONF\_mailgun\_domain\_name](#rcconf_mailgun_domain_name)
    - [RCCONF\_db\_binlog\_format](#rcconf_db_binlog_format)
    - [RCCONF\_default\_csv\_delimiter](#rcconf_default_csv_delimiter)
    - [RCCONF\_project\_dashboard\_allow\_public](#rcconf_project_dashboard_allow_public)
    - [RCCONF\_project\_dashboard\_min\_data\_points](#rcconf_project_dashboard_min_data_points)
    - [RCCONF\_oauth2\_azure\_ad\_client\_id](#rcconf_oauth2_azure_ad_client_id)
    - [RCCONF\_oauth2\_azure\_ad\_client\_secret](#rcconf_oauth2_azure_ad_client_secret)
    - [RCCONF\_oauth2\_azure\_ad\_primary\_admin](#rcconf_oauth2_azure_ad_primary_admin)
    - [RCCONF\_oauth2\_azure\_ad\_secondary\_admin](#rcconf_oauth2_azure_ad_secondary_admin)
    - [RCCONF\_fhir\_cdp\_allow\_auto\_adjudication](#rcconf_fhir_cdp_allow_auto_adjudication)
    - [RCCONF\_field\_bank\_enabled](#rcconf_field_bank_enabled)
    - [RCCONF\_sendgrid\_api\_key](#rcconf_sendgrid_api_key)
    - [RCCONF\_fhir\_break\_the\_glass\_enabled](#rcconf_fhir_break_the_glass_enabled)
    - [RCCONF\_fhir\_break\_the\_glass\_ehr\_usertype](#rcconf_fhir_break_the_glass_ehr_usertype)
    - [RCCONF\_fhir\_break\_the\_glass\_token\_usertype](#rcconf_fhir_break_the_glass_token_usertype)
    - [RCCONF\_fhir\_break\_the\_glass\_token\_username](#rcconf_fhir_break_the_glass_token_username)
    - [RCCONF\_fhir\_break\_the\_glass\_token\_password](#rcconf_fhir_break_the_glass_token_password)
    - [RCCONF\_fhir\_break\_the\_glass\_username\_token\_base\_url](#rcconf_fhir_break_the_glass_username_token_base_url)
    - [RCCONF\_record\_locking\_pdf\_vault\_filesystem\_type](#rcconf_record_locking_pdf_vault_filesystem_type)
    - [RCCONF\_record\_locking\_pdf\_vault\_filesystem\_host](#rcconf_record_locking_pdf_vault_filesystem_host)
    - [RCCONF\_record\_locking\_pdf\_vault\_filesystem\_username](#rcconf_record_locking_pdf_vault_filesystem_username)
    - [RCCONF\_record\_locking\_pdf\_vault\_filesystem\_password](#rcconf_record_locking_pdf_vault_filesystem_password)
    - [RCCONF\_record\_locking\_pdf\_vault\_filesystem\_path](#rcconf_record_locking_pdf_vault_filesystem_path)
    - [RCCONF\_record\_locking\_pdf\_vault\_filesystem\_private\_key\_path](#rcconf_record_locking_pdf_vault_filesystem_private_key_path)
    - [RCCONF\_mandrill\_api\_key](#rcconf_mandrill_api_key)
    - [RCCONF\_shibboleth\_table\_config](#rcconf_shibboleth_table_config)
    - [RCCONF\_survey\_pid\_create\_project](#rcconf_survey_pid_create_project)
    - [RCCONF\_survey\_pid\_move\_to\_prod\_status](#rcconf_survey_pid_move_to_prod_status)
    - [RCCONF\_survey\_pid\_move\_to\_analysis\_status](#rcconf_survey_pid_move_to_analysis_status)
    - [RCCONF\_survey\_pid\_mark\_completed](#rcconf_survey_pid_mark_completed)
    - [RCCONF\_email\_alerts\_converter\_enabled](#rcconf_email_alerts_converter_enabled)
    - [RCCONF\_use\_email\_display\_name](#rcconf_use_email_display_name)
    - [RCCONF\_alerts\_allow\_phone\_variables](#rcconf_alerts_allow_phone_variables)
    - [RCCONF\_alerts\_allow\_phone\_freeform](#rcconf_alerts_allow_phone_freeform)
    - [RCCONF\_fhir\_standalone\_authentication\_flow](#rcconf_fhir_standalone_authentication_flow)
    - [RCCONF\_external\_modules\_allow\_activation\_user\_request](#rcconf_external_modules_allow_activation_user_request)
    - [RCCONF\_dkim\_private\_key](#rcconf_dkim_private_key)
    - [RCCONF\_enable\_url\_shortener\_redcap](#rcconf_enable_url_shortener_redcap)
    - [RCCONF\_from\_email\_domain\_exclude](#rcconf_from_email_domain_exclude)
    - [RCCONF\_fhir\_include\_email\_address](#rcconf_fhir_include_email_address)
    - [RCCONF\_file\_upload\_vault\_filesystem\_type](#rcconf_file_upload_vault_filesystem_type)
    - [RCCONF\_file\_upload\_vault\_filesystem\_host](#rcconf_file_upload_vault_filesystem_host)
    - [RCCONF\_file\_upload\_vault\_filesystem\_username](#rcconf_file_upload_vault_filesystem_username)
    - [RCCONF\_file\_upload\_vault\_filesystem\_password](#rcconf_file_upload_vault_filesystem_password)
    - [RCCONF\_file\_upload\_vault\_filesystem\_path](#rcconf_file_upload_vault_filesystem_path)
    - [RCCONF\_file\_upload\_vault\_filesystem\_private\_key\_path](#rcconf_file_upload_vault_filesystem_private_key_path)
    - [RCCONF\_file\_upload\_versioning\_enabled](#rcconf_file_upload_versioning_enabled)
    - [RCCONF\_file\_upload\_versioning\_global\_enabled](#rcconf_file_upload_versioning_global_enabled)
    - [RCCONF\_allow\_outbound\_http](#rcconf_allow_outbound_http)
    - [RCCONF\_drw\_upload\_option\_enabled](#rcconf_drw_upload_option_enabled)
    - [RCCONF\_pdf\_econsent\_system\_custom\_text](#rcconf_pdf_econsent_system_custom_text)
    - [RCCONF\_alerts\_email\_freeform\_domain\_allowlist](#rcconf_alerts_email_freeform_domain_allowlist)
    - [RCCONF\_alerts\_allow\_email\_variables](#rcconf_alerts_allow_email_variables)
    - [RCCONF\_alerts\_allow\_email\_freeform](#rcconf_alerts_allow_email_freeform)
    - [RCCONF\_azure\_quickstart](#rcconf_azure_quickstart)
    - [RCCONF\_google\_recaptcha\_site\_key](#rcconf_google_recaptcha_site_key)
    - [RCCONF\_google\_recaptcha\_secret\_key](#rcconf_google_recaptcha_secret_key)
    - [RCCONF\_aws\_quickstart](#rcconf_aws_quickstart)
    - [RCCONF\_user\_messaging\_prevent\_admin\_messaging](#rcconf_user_messaging_prevent_admin_messaging)
    - [RCCONF\_homepage\_announcement\_login](#rcconf_homepage_announcement_login)
    - [RCCONF\_azure\_app\_name](#rcconf_azure_app_name)
    - [RCCONF\_azure\_app\_secret](#rcconf_azure_app_secret)
    - [RCCONF\_azure\_container](#rcconf_azure_container)
    - [RCCONF\_redcap\_updates\_community\_user](#rcconf_redcap_updates_community_user)
    - [RCCONF\_redcap\_updates\_community\_password](#rcconf_redcap_updates_community_password)
    - [RCCONF\_redcap\_updates\_user](#rcconf_redcap_updates_user)
    - [RCCONF\_redcap\_updates\_password](#rcconf_redcap_updates_password)
    - [RCCONF\_redcap\_updates\_password\_encrypted](#rcconf_redcap_updates_password_encrypted)
    - [RCCONF\_redcap\_updates\_available](#rcconf_redcap_updates_available)
    - [RCCONF\_redcap\_updates\_available\_last\_check](#rcconf_redcap_updates_available_last_check)
    - [RCCONF\_realtime\_webservice\_convert\_timestamp\_from\_gmt](#rcconf_realtime_webservice_convert_timestamp_from_gmt)
    - [RCCONF\_fhir\_convert\_timestamp\_from\_gmt](#rcconf_fhir_convert_timestamp_from_gmt)
    - [RCCONF\_db\_collation](#rcconf_db_collation)
    - [RCCONF\_db\_character\_set](#rcconf_db_character_set)
    - [RCCONF\_external\_modules\_updates\_available](#rcconf_external_modules_updates_available)
    - [RCCONF\_external\_modules\_updates\_available\_last\_check](#rcconf_external_modules_updates_available_last_check)
    - [RCCONF\_pdf\_econsent\_system\_ip](#rcconf_pdf_econsent_system_ip)
    - [RCCONF\_pdf\_econsent\_filesystem\_type](#rcconf_pdf_econsent_filesystem_type)
    - [RCCONF\_pdf\_econsent\_filesystem\_host](#rcconf_pdf_econsent_filesystem_host)
    - [RCCONF\_pdf\_econsent\_filesystem\_username](#rcconf_pdf_econsent_filesystem_username)
    - [RCCONF\_pdf\_econsent\_filesystem\_password](#rcconf_pdf_econsent_filesystem_password)
    - [RCCONF\_pdf\_econsent\_filesystem\_path](#rcconf_pdf_econsent_filesystem_path)
    - [RCCONF\_pdf\_econsent\_filesystem\_private\_key\_path](#rcconf_pdf_econsent_filesystem_private_key_path)
    - [RCCONF\_pdf\_econsent\_system\_enabled](#rcconf_pdf_econsent_system_enabled)
    - [RCCONF\_enable\_edit\_prod\_repeating\_setup](#rcconf_enable_edit_prod_repeating_setup)
    - [RCCONF\_user\_sponsor\_set\_expiration\_days](#rcconf_user_sponsor_set_expiration_days)
    - [RCCONF\_user\_sponsor\_dashboard\_enable](#rcconf_user_sponsor_dashboard_enable)
    - [RCCONF\_clickjacking\_prevention](#rcconf_clickjacking_prevention)
    - [RCCONF\_external\_module\_alt\_paths](#rcconf_external_module_alt_paths)
    - [RCCONF\_aafAccessUrl](#rcconf_aafaccessurl)
    - [RCCONF\_aafAllowLocalsCreateDB](#rcconf_aafallowlocalscreatedb)
    - [RCCONF\_aafAud](#rcconf_aafaud)
    - [RCCONF\_aafDisplayOnEmailUsers](#rcconf_aafdisplayonemailusers)
    - [RCCONF\_aafIss](#rcconf_aafiss)
    - [RCCONF\_aafPrimaryField](#rcconf_aafprimaryfield)
    - [RCCONF\_aafScopeTarget](#rcconf_aafscopetarget)
    - [RCCONF\_external\_modules\_project\_custom\_text](#rcconf_external_modules_project_custom_text)
    - [RCCONF\_is\_development\_server](#rcconf_is_development_server)
    - [RCCONF\_fhir\_data\_mart\_create\_project](#rcconf_fhir_data_mart_create_project)
    - [RCCONF\_fhir\_data\_fetch\_interval](#rcconf_fhir_data_fetch_interval)
    - [RCCONF\_fhir\_url\_user\_access](#rcconf_fhir_url_user_access)
    - [RCCONF\_fhir\_custom\_text](#rcconf_fhir_custom_text)
    - [RCCONF\_fhir\_display\_info\_project\_setup](#rcconf_fhir_display_info_project_setup)
    - [RCCONF\_fhir\_source\_system\_custom\_name](#rcconf_fhir_source_system_custom_name)
    - [RCCONF\_fhir\_user\_rights\_super\_users\_only](#rcconf_fhir_user_rights_super_users_only)
    - [RCCONF\_fhir\_stop\_fetch\_inactivity\_days](#rcconf_fhir_stop_fetch_inactivity_days)
    - [RCCONF\_fhir\_ddp\_enabled](#rcconf_fhir_ddp_enabled)
    - [RCCONF\_api\_token\_request\_type](#rcconf_api_token_request_type)
    - [RCCONF\_fhir\_endpoint\_authorize\_url](#rcconf_fhir_endpoint_authorize_url)
    - [RCCONF\_fhir\_endpoint\_token\_url](#rcconf_fhir_endpoint_token_url)
    - [RCCONF\_fhir\_ehr\_mrn\_identifier](#rcconf_fhir_ehr_mrn_identifier)
    - [RCCONF\_fhir\_identity\_provider](#rcconf_fhir_identity_provider)
    - [RCCONF\_fhir\_client\_id](#rcconf_fhir_client_id)
    - [RCCONF\_fhir\_client\_secret](#rcconf_fhir_client_secret)
    - [RCCONF\_fhir\_endpoint\_base\_url](#rcconf_fhir_endpoint_base_url)
    - [RCCONF\_report\_stats\_url](#rcconf_report_stats_url)
    - [RCCONF\_user\_messaging\_enabled](#rcconf_user_messaging_enabled)
    - [RCCONF\_auto\_prod\_changes\_check\_identifiers](#rcconf_auto_prod_changes_check_identifiers)
    - [RCCONF\_bioportal\_api\_url](#rcconf_bioportal_api_url)
    - [RCCONF\_send\_emails\_admin\_tasks](#rcconf_send_emails_admin_tasks)
    - [RCCONF\_display\_project\_xml\_backup\_option](#rcconf_display_project_xml_backup_option)
    - [RCCONF\_cross\_domain\_access\_control](#rcconf_cross_domain_access_control)
    - [RCCONF\_google\_cloud\_storage\_edocs\_bucket](#rcconf_google_cloud_storage_edocs_bucket)
    - [RCCONF\_google\_cloud\_storage\_temp\_bucket](#rcconf_google_cloud_storage_temp_bucket)
    - [RCCONF\_amazon\_s3\_endpoint](#rcconf_amazon_s3_endpoint)
    - [RCCONF\_proxy\_username\_password](#rcconf_proxy_username_password)
    - [RCCONF\_homepage\_contact\_url](#rcconf_homepage_contact_url)
    - [RCCONF\_bioportal\_api\_token](#rcconf_bioportal_api_token)
    - [RCCONF\_two\_factor\_auth\_ip\_range\_alt](#rcconf_two_factor_auth_ip_range_alt)
    - [RCCONF\_two\_factor\_auth\_trust\_period\_days\_alt](#rcconf_two_factor_auth_trust_period_days_alt)
    - [RCCONF\_two\_factor\_auth\_trust\_period\_days](#rcconf_two_factor_auth_trust_period_days)
    - [RCCONF\_two\_factor\_auth\_email\_enabled](#rcconf_two_factor_auth_email_enabled)
    - [RCCONF\_two\_factor\_auth\_authenticator\_enabled](#rcconf_two_factor_auth_authenticator_enabled)
    - [RCCONF\_two\_factor\_auth\_ip\_check\_enabled](#rcconf_two_factor_auth_ip_check_enabled)
    - [RCCONF\_two\_factor\_auth\_ip\_range](#rcconf_two_factor_auth_ip_range)
    - [RCCONF\_two\_factor\_auth\_ip\_range\_include\_private](#rcconf_two_factor_auth_ip_range_include_private)
    - [RCCONF\_two\_factor\_auth\_duo\_enabled](#rcconf_two_factor_auth_duo_enabled)
    - [RCCONF\_two\_factor\_auth\_duo\_ikey](#rcconf_two_factor_auth_duo_ikey)
    - [RCCONF\_two\_factor\_auth\_duo\_skey](#rcconf_two_factor_auth_duo_skey)
    - [RCCONF\_two\_factor\_auth\_duo\_hostname](#rcconf_two_factor_auth_duo_hostname)
    - [RCCONF\_bioportal\_ontology\_list\_cache\_time](#rcconf_bioportal_ontology_list_cache_time)
    - [RCCONF\_bioportal\_ontology\_list](#rcconf_bioportal_ontology_list)
    - [RCCONF\_redcap\_survey\_base\_url](#rcconf_redcap_survey_base_url)
    - [RCCONF\_enable\_ontology\_auto\_suggest](#rcconf_enable_ontology_auto_suggest)
    - [RCCONF\_enable\_survey\_text\_to\_speech](#rcconf_enable_survey_text_to_speech)
    - [RCCONF\_enable\_field\_attachment\_video\_url](#rcconf_enable_field_attachment_video_url)
    - [RCCONF\_google\_oauth2\_client\_id](#rcconf_google_oauth2_client_id)
    - [RCCONF\_google\_oauth2\_client\_secret](#rcconf_google_oauth2_client_secret)
    - [RCCONF\_two\_factor\_auth\_twilio\_enabled](#rcconf_two_factor_auth_twilio_enabled)
    - [RCCONF\_two\_factor\_auth\_twilio\_account\_sid](#rcconf_two_factor_auth_twilio_account_sid)
    - [RCCONF\_two\_factor\_auth\_twilio\_auth\_token](#rcconf_two_factor_auth_twilio_auth_token)
    - [RCCONF\_two\_factor\_auth\_twilio\_from\_number](#rcconf_two_factor_auth_twilio_from_number)
    - [RCCONF\_two\_factor\_auth\_twilio\_from\_number\_voice\_alt](#rcconf_two_factor_auth_twilio_from_number_voice_alt)
    - [RCCONF\_two\_factor\_auth\_enabled](#rcconf_two_factor_auth_enabled)
    - [RCCONF\_allow\_kill\_mysql\_process](#rcconf_allow_kill_mysql_process)
    - [RCCONF\_mobile\_app\_enabled](#rcconf_mobile_app_enabled)
    - [RCCONF\_mycap\_enabled\_global](#rcconf_mycap_enabled_global)
    - [RCCONF\_mycap\_enable\_type](#rcconf_mycap_enable_type)
    - [RCCONF\_twilio\_display\_info\_project\_setup](#rcconf_twilio_display_info_project_setup)
    - [RCCONF\_twilio\_enabled\_global](#rcconf_twilio_enabled_global)
    - [RCCONF\_twilio\_enabled\_by\_super\_users\_only](#rcconf_twilio_enabled_by_super_users_only)
    - [RCCONF\_field\_comment\_log\_enabled\_default](#rcconf_field_comment_log_enabled_default)
    - [RCCONF\_from\_email](#rcconf_from_email)
    - [RCCONF\_promis\_enabled](#rcconf_promis_enabled)
    - [RCCONF\_promis\_api\_base\_url](#rcconf_promis_api_base_url)
    - [RCCONF\_sams\_logout](#rcconf_sams_logout)
    - [RCCONF\_promis\_registration\_id](#rcconf_promis_registration_id)
    - [RCCONF\_promis\_token](#rcconf_promis_token)
    - [RCCONF\_hook\_functions\_file](#rcconf_hook_functions_file)
    - [RCCONF\_project\_encoding](#rcconf_project_encoding)
    - [RCCONF\_default\_datetime\_format](#rcconf_default_datetime_format)
    - [RCCONF\_default\_number\_format\_decimal](#rcconf_default_number_format_decimal)
    - [RCCONF\_default\_number\_format\_thousands\_sep](#rcconf_default_number_format_thousands_sep)
    - [RCCONF\_homepage\_announcement](#rcconf_homepage_announcement)
    - [RCCONF\_password\_algo](#rcconf_password_algo)
    - [RCCONF\_password\_recovery\_custom\_text](#rcconf_password_recovery_custom_text)
    - [RCCONF\_user\_access\_dashboard\_enable](#rcconf_user_access_dashboard_enable)
    - [RCCONF\_user\_access\_dashboard\_custom\_notification](#rcconf_user_access_dashboard_custom_notification)
    - [RCCONF\_suspend\_users\_inactive\_send\_email](#rcconf_suspend_users_inactive_send_email)
    - [RCCONF\_suspend\_users\_inactive\_days](#rcconf_suspend_users_inactive_days)
    - [RCCONF\_suspend\_users\_inactive\_type](#rcconf_suspend_users_inactive_type)
    - [RCCONF\_page\_hit\_threshold\_per\_minute](#rcconf_page_hit_threshold_per_minute)
    - [RCCONF\_enable\_http\_compression](#rcconf_enable_http_compression)
    - [RCCONF\_realtime\_webservice\_data\_fetch\_interval](#rcconf_realtime_webservice_data_fetch_interval)
    - [RCCONF\_realtime\_webservice\_url\_metadata](#rcconf_realtime_webservice_url_metadata)
    - [RCCONF\_realtime\_webservice\_url\_data](#rcconf_realtime_webservice_url_data)
    - [RCCONF\_realtime\_webservice\_url\_user\_access](#rcconf_realtime_webservice_url_user_access)
    - [RCCONF\_realtime\_webservice\_global\_enabled](#rcconf_realtime_webservice_global_enabled)
    - [RCCONF\_realtime\_webservice\_custom\_text](#rcconf_realtime_webservice_custom_text)
    - [RCCONF\_realtime\_webservice\_display\_info\_project\_setup](#rcconf_realtime_webservice_display_info_project_setup)
    - [RCCONF\_realtime\_webservice\_source\_system\_custom\_name](#rcconf_realtime_webservice_source_system_custom_name)
    - [RCCONF\_realtime\_webservice\_user\_rights\_super\_users\_only](#rcconf_realtime_webservice_user_rights_super_users_only)
    - [RCCONF\_realtime\_webservice\_stop\_fetch\_inactivity\_days](#rcconf_realtime_webservice_stop_fetch_inactivity_days)
    - [RCCONF\_amazon\_s3\_key](#rcconf_amazon_s3_key)
    - [RCCONF\_amazon\_s3\_secret](#rcconf_amazon_s3_secret)
    - [RCCONF\_amazon\_s3\_bucket](#rcconf_amazon_s3_bucket)
    - [RCCONF\_system\_offline\_message](#rcconf_system_offline_message)
    - [RCCONF\_openid\_provider\_url](#rcconf_openid_provider_url)
    - [RCCONF\_openid\_provider\_name](#rcconf_openid_provider_name)
    - [RCCONF\_file\_attachment\_upload\_max](#rcconf_file_attachment_upload_max)
    - [RCCONF\_data\_entry\_trigger\_enabled](#rcconf_data_entry_trigger_enabled)
    - [RCCONF\_redcap\_base\_url\_display\_error\_on\_mismatch](#rcconf_redcap_base_url_display_error_on_mismatch)
    - [RCCONF\_email\_domain\_allowlist](#rcconf_email_domain_allowlist)
    - [RCCONF\_helpfaq\_custom\_text](#rcconf_helpfaq_custom_text)
    - [RCCONF\_randomization\_global](#rcconf_randomization_global)
    - [RCCONF\_login\_custom\_text](#rcconf_login_custom_text)
    - [RCCONF\_auto\_prod\_changes](#rcconf_auto_prod_changes)
    - [RCCONF\_enable\_edit\_prod\_events](#rcconf_enable_edit_prod_events)
    - [RCCONF\_allow\_create\_db\_default](#rcconf_allow_create_db_default)
    - [RCCONF\_api\_enabled](#rcconf_api_enabled)
    - [RCCONF\_auth\_meth\_global](#rcconf_auth_meth_global)
    - [RCCONF\_auto\_report\_stats](#rcconf_auto_report_stats)
    - [RCCONF\_autologout\_timer](#rcconf_autologout_timer)
    - [RCCONF\_certify\_text\_create](#rcconf_certify_text_create)
    - [RCCONF\_certify\_text\_prod](#rcconf_certify_text_prod)
    - [RCCONF\_homepage\_custom\_text](#rcconf_homepage_custom_text)
    - [RCCONF\_dts\_enabled\_global](#rcconf_dts_enabled_global)
    - [RCCONF\_display\_project\_logo\_institution](#rcconf_display_project_logo_institution)
    - [RCCONF\_display\_today\_now\_button](#rcconf_display_today_now_button)
    - [RCCONF\_edoc\_field\_option\_enabled](#rcconf_edoc_field_option_enabled)
    - [RCCONF\_edoc\_upload\_max](#rcconf_edoc_upload_max)
    - [RCCONF\_edoc\_storage\_option](#rcconf_edoc_storage_option)
    - [RCCONF\_file\_repository\_upload\_max](#rcconf_file_repository_upload_max)
    - [RCCONF\_file\_repository\_enabled](#rcconf_file_repository_enabled)
    - [RCCONF\_edoc\_path](#rcconf_edoc_path)
    - [RCCONF\_enable\_edit\_survey\_response](#rcconf_enable_edit_survey_response)
    - [RCCONF\_enable\_plotting](#rcconf_enable_plotting)
    - [RCCONF\_enable\_plotting\_survey\_results](#rcconf_enable_plotting_survey_results)
    - [RCCONF\_enable\_projecttype\_singlesurvey](#rcconf_enable_projecttype_singlesurvey)
    - [RCCONF\_enable\_projecttype\_forms](#rcconf_enable_projecttype_forms)
    - [RCCONF\_enable\_projecttype\_singlesurveyforms](#rcconf_enable_projecttype_singlesurveyforms)
    - [RCCONF\_enable\_url\_shortener](#rcconf_enable_url_shortener)
    - [RCCONF\_enable\_user\_allowlist](#rcconf_enable_user_allowlist)
    - [RCCONF\_logout\_fail\_limit](#rcconf_logout_fail_limit)
    - [RCCONF\_logout\_fail\_window](#rcconf_logout_fail_window)
    - [RCCONF\_footer\_links](#rcconf_footer_links)
    - [RCCONF\_footer\_text](#rcconf_footer_text)
    - [RCCONF\_google\_translate\_enabled](#rcconf_google_translate_enabled)
    - [RCCONF\_googlemap\_key](#rcconf_googlemap_key)
    - [RCCONF\_grant\_cite](#rcconf_grant_cite)
    - [RCCONF\_headerlogo](#rcconf_headerlogo)
    - [RCCONF\_homepage\_contact](#rcconf_homepage_contact)
    - [RCCONF\_homepage\_contact\_email](#rcconf_homepage_contact_email)
    - [RCCONF\_homepage\_grant\_cite](#rcconf_homepage_grant_cite)
    - [RCCONF\_identifier\_keywords](#rcconf_identifier_keywords)
    - [RCCONF\_institution](#rcconf_institution)
    - [RCCONF\_language\_global](#rcconf_language_global)
    - [RCCONF\_login\_autocomplete\_disable](#rcconf_login_autocomplete_disable)
    - [RCCONF\_login\_logo](#rcconf_login_logo)
    - [RCCONF\_my\_profile\_enable\_edit](#rcconf_my_profile_enable_edit)
    - [RCCONF\_my\_profile\_enable\_primary\_email\_edit](#rcconf_my_profile_enable_primary_email_edit)
    - [RCCONF\_password\_history\_limit](#rcconf_password_history_limit)
    - [RCCONF\_password\_reset\_duration](#rcconf_password_reset_duration)
    - [RCCONF\_project\_contact\_email](#rcconf_project_contact_email)
    - [RCCONF\_project\_contact\_name](#rcconf_project_contact_name)
    - [RCCONF\_project\_language](#rcconf_project_language)
    - [RCCONF\_proxy\_hostname](#rcconf_proxy_hostname)
    - [RCCONF\_pub\_matching\_enabled](#rcconf_pub_matching_enabled)
    - [RCCONF\_redcap\_base\_url](#rcconf_redcap_base_url)
    - [RCCONF\_pub\_matching\_emails](#rcconf_pub_matching_emails)
    - [RCCONF\_pub\_matching\_email\_days](#rcconf_pub_matching_email_days)
    - [RCCONF\_pub\_matching\_email\_limit](#rcconf_pub_matching_email_limit)
    - [RCCONF\_pub\_matching\_email\_text](#rcconf_pub_matching_email_text)
    - [RCCONF\_pub\_matching\_email\_subject](#rcconf_pub_matching_email_subject)
    - [RCCONF\_pub\_matching\_institution](#rcconf_pub_matching_institution)
    - [RCCONF\_sendit\_enabled](#rcconf_sendit_enabled)
    - [RCCONF\_sendit\_upload\_max](#rcconf_sendit_upload_max)
    - [RCCONF\_shared\_library\_enabled](#rcconf_shared_library_enabled)
    - [RCCONF\_shibboleth\_logout](#rcconf_shibboleth_logout)
    - [RCCONF\_shibboleth\_username\_field](#rcconf_shibboleth_username_field)
    - [RCCONF\_site\_org\_type](#rcconf_site_org_type)
    - [RCCONF\_superusers\_only\_create\_project](#rcconf_superusers_only_create_project)
    - [RCCONF\_superusers\_only\_move\_to\_prod](#rcconf_superusers_only_move_to_prod)
    - [RCCONF\_system\_offline](#rcconf_system_offline)
    - [RCCONF\_cache\_storage\_system](#rcconf_cache_storage_system)


# PHP

```env
TZ # default: UTC
PHP_MEMORY_LIMIT # default: 2048M
PHP_INI_SCAN_DIR # default: /php/custom_inis/
```
# Apache
```env
SERVER_NAME # Default: localhost
SERVER_ADMIN # Default: root
SERVER_ALIAS # Default:localhost
APACHE_RUN_HOME # Default: /var/www
APACHE_DOCUMENT_ROOT # Default: /var/www/html
APACHE_ERROR_LOG # Default: /dev/stdout
APACHE_ACCESS_LOG # Default: /dev/stdout
```

# www-data user and group ID

By default the system user that runs the apache process is www-data with the uid/gid `33`/`33`.
You can changes this with the two env vars:

`WWW_DATA_UID`  

`WWW_DATA_GID`

This way you could give the REDCap files the same ownership as your host system user.


# Run custom or upgrade SQL Scripts at boot


`AT_BOOT_RUN_SQL_SCRIPTS_FROM_LOCATION` 

With the env var `AT_BOOT_RUN_SQL_SCRIPTS_FROM_LOCATION` you can set a location to a local single file, a local directory or a remote http file or directory.

If the file(s) end with the extension `sql` they will be pickd up and run at the mysql database at next (re-)boot.  

Each SQL file(s) will be remembered (by hash) and not run again at the following (re-)boot.

This can be handy for RedCap upgrade procedures, that need you to run a sql script. see the document [REDCAP_UPGRADE.md](/REDCAP_UPGRADE.md) for more info
  
  
Defaults to `/opt/redcap-docker/sql_scripts_run_once`

# Fix REDCap source files/directory permissions

`FIX_REDCAP_DIR_PERMISSIONS`

Set this to false, if the container should not apply the user `www-data` to be the owner of the REDCap source directory and files on startup.

Defaults to `true`

> [!TIP]
> If you are not happy with the UID/GID of the user www-data have a look at [`WWW_DATA_UID`/`WWW_DATA_GID`](#www-data-user-and-group-id).


# REDCap database connection environment variable

```env
DB_PORT     # Default: null
DB_HOSTNAME # Default: ''
DB_NAME     # Default: ''
DB_USERNAME # Default: ''
DB_PASSWORD # Default: ''

# The path name to the key file.
DB_SSL_KEY_PATH      # Default: ''

# The path name to the certificate file.
DB_SSL_CERT_PATH     # Default: ''

# The path name to the certificate authority file.
DB_SSL_CA_FILE_PATH  # Default: ''

# The pathname to a directory that contains trusted SSL CA certificates in PEM format. 
DB_SSL_CA_DIR_PATH  # Default: ''

# A list of allowable ciphers to use for SSL encryption.
DB_SSL_ALGOS         # Default: null

DB_SSL_VERIFY_SERVER # Default = false

DB_SALT # Default: ''
```


## REDCap Data Transfer Services
```env
DTS_HOSTNAME
DTS_DB
DTS_USERNAME
DTS_PASSWORD
```

# User provisioning

This container image can prefill the database with table users. for more details have a look at [User provisioning](USER_PROV.md)

Available env vars:


`ENABLE_USER_PROV` default `true` -  siwtch to false to disable user provisoning completly

`USER_PROV_FILE_DIR` default `/opt/redcap-docker/users` - A path that will be scanned for json or yaml files with user data for the user provisioning

`USER_PROV_OVERWRITE_EXISTING` default `false` - if set top true existing users in the REDCap database with the same username will be overwriten

`USER_PROV` default none - Multiple users data as json. see [User provisioning](USER_PROV.md) for details and format

`USER_PROV_``*` default none -  User data as json. see [User provisioning](USER_PROV.md) for details and format


# REDCap installation

This image tries to automate the "installation" of REDCap. In REDCap context "installation" means: Deploying the database schema and inserting some basic data.
We try to extract the SQL scripts from the REDCap source you provided. A second option is that you provide the generated SQL Script yourself (like in a classic REDCap installation).

## Option 1 - automated installation 

The default option to install REDCap. Runs the build-in install script, from the mounted redcap source, if there is not a `redcap_config`-table in the existing database. 
(If a `redcap_config`-table is existing, this container makes the assumption REDCap is allready installed. Which may not true in all cases, in which case you have to install manually)

`REDCAP_INSTALL_ENABLE`

can be `true` or `false`
defaults to `true`

## Option 2 - Installation with bring-your-own SQL install Script

With this docker image you can provide the installation script generated by REDCaps `/install.php` via a path defined in the env var

`REDCAP_INSTALL_SQL_SCRIPT_PATH`

It defaults to `/config/redcap/install/install.sql` 

**Hint**: You still need to set `REDCAP_INSTALL_ENABLE` to true

# REDCap upgrade

ToDo: This part is untested and undocumented yet.

# REDCap Basic Admin tasks

## suspend site_admin

```env
REDCAP_SUSPEND_SITE_ADMIN # Default: True
```


# MSMTP

You can set all msmtp config vars via environment. Just prefix the msmpt config commands/params `MSMTP_`

For a list of all config commands see
* https://marlam.de/msmtp/msmtp.html#General-commands
* https://marlam.de/msmtp/msmtp.html#Authentication-commands
* https://marlam.de/msmtp/msmtp.html#TLS-commands
* https://marlam.de/msmtp/msmtp.html#Commands-specific-to-sendmail-mode

Example for sending mails via a Hetzner mail account: 

```env
MSMTP_from=redcap-system@wy-corp.earth
MSMTP_host=mail.your-server.de
MSMTP_port=587
MSMTP_auth=on
MSMTP_user=redcap-system@wy-corp.earth
MSMTP_password=mytotalsecretpassword
MSMTP_tls=on
MSMTP_tls_starttls=on
MSMTP_syslog=on
RCCONF_from_email=redcap-system@wy-corp.earth
```
# Cron

`CRON_MODE` - default `false`  
If you set `CRON_MODE` to true the container will not start the REDCap webserver but run the REDCap cron job in an intervall.
  
`CRON_INTERVAL` - default `*/5 * * * *`  
With `CRON_INTERVAL` you can define the interval how often the REDCap cronjob should run
  
`CRON_RUN_JOB_ON_START` - default `false`  
If you want to run the job as soon the container starts you set this to true

see the [cron example](examples/instance_with_cron) for a docker compose exmaple.

# REDCap Application Config vars

This container instance enables you to set any configuration variable that is in the table `redcap_config` (AFAIK: everything you can set in the Webinterface as admin in the "Control Center"). this way we are able to create REDCap deployments in a modern manner. 

## APPLY_RCCONF_VARIABLES

If `APPLY_RCCONF_VARIABLES` is set to true, the env var config variables, that are supplied to the container, will be set on every container start (overwriting any configurations that were set via webinterface since last boot of the instance).
If you only want to apply the config (via env vars) once, set `APPLY_RCCONF_VARIABLES` to `false` after setting up your REDCap instance.


```env
APPLY_RCCONF_VARIABLES # Default: False
```

## Possible config variables
(As of REDCap version `14.0.30`)
(completeness not guaranteed)

Unfortunately there is no central documentation for all REDCap config variables. if you want to find out what a config variable does; examining the "Control Center" in a running REDCap instance is a good start.

### RCCONF_mtb_enabled
```
RCCONF_mtb_enabled
```
Database default: `0`

### RCCONF_cache_files_filesystem_path
```
RCCONF_cache_files_filesystem_path
```
 Database default: ``

### RCCONF_allow_auto_variable_naming 
```
RCCONF_allow_auto_variable_naming 
```
Database default: `2`

### RCCONF_mailgun_api_endpoint 
```
RCCONF_mailgun_api_endpoint 
```
Database default: ``

### RCCONF_openid_connect_additional_scope
```
RCCONF_openid_connect_additional_scope
```
Database default: ``

### RCCONF_read_replica_enable
```
RCCONF_read_replica_enable
```
Database default: `0`

### RCCONF_azure_comm_api_endpoint
```
RCCONF_azure_comm_api_endpoint
```
Database default: ``

### RCCONF_azure_comm_api_key 
```
RCCONF_azure_comm_api_key 
```
Database default: ``

### RCCONF_fhir_custom_auth_params
```
RCCONF_fhir_custom_auth_params
```
Database default: ``

### RCCONF_fhir_custom_mapping_file_id
```
RCCONF_fhir_custom_mapping_file_id
```
Database default: ``

### RCCONF_oauth2_azure_ad_tenant 
```
RCCONF_oauth2_azure_ad_tenant 
```
Database default: `common`

### RCCONF_display_inline_pdf_in_pdf
```
RCCONF_display_inline_pdf_in_pdf
```
Database default: `1`

### RCCONF_mosio_enabled_global 
```
RCCONF_mosio_enabled_global 
```
Database default: `1`

### RCCONF_mosio_display_info_project_setup 
```
RCCONF_mosio_display_info_project_setup 
```
Database default: `0`

### RCCONF_mosio_enabled_by_super_users_only
```
RCCONF_mosio_enabled_by_super_users_only
```
Database default: `0`

### RCCONF_rich_text_attachment_embed_enabled 
```
RCCONF_rich_text_attachment_embed_enabled 
```
Database default: `1`

### RCCONF_oauth2_azure_ad_name 
```
RCCONF_oauth2_azure_ad_name 
```
Database default: ``

### RCCONF_admin_email_external_user_creation 
```
RCCONF_admin_email_external_user_creation 
```
Database default: `0`

### RCCONF_user_welcome_email_external_user_creation
```
RCCONF_user_welcome_email_external_user_creation
```
Database default: `0`

### RCCONF_openid_connect_response_type 
```
RCCONF_openid_connect_response_type 
```
Database default: `query`

### RCCONF_restricted_upload_file_types 
```
RCCONF_restricted_upload_file_types 
```
Database default: `ade, adp, apk, appx, appxbundle, bat, cab, chm, cmd, com, cpl, diagcab, diagcfg, diagpack, dll, dmg, ex, exe, hta, img, ins, iso, isp, jar, jnlp, js, jse, lib, lnk, mde, msc, msi, msix, msixbundle, msp, mst, nsh, php, pif, ps1, scr, sct, shb, sys, vb, vbe, vbs, vhd, vxd, wsc, wsf, wsh, xll`

### RCCONF_file_repository_allow_public_link
```
RCCONF_file_repository_allow_public_link
```
Database default: `1`

### RCCONF_file_repository_total_size 
```
RCCONF_file_repository_total_size 
```
Database default: ``

### RCCONF_contact_admin_button_url 
```
RCCONF_contact_admin_button_url 
```
Database default: ``

### RCCONF_rich_text_image_embed_enabled
```
RCCONF_rich_text_image_embed_enabled
```
Database default: `1`

### RCCONF_two_factor_auth_enforce_table_users_only 
```
RCCONF_two_factor_auth_enforce_table_users_only 
```
Database default: `0`

### RCCONF_openid_connect_username_attribute
```
RCCONF_openid_connect_username_attribute
```
Database default: `username`

### RCCONF_calendar_feed_enabled_global 
```
RCCONF_calendar_feed_enabled_global 
```
Database default: `1`

### RCCONF_sendgrid_enabled_global
```
RCCONF_sendgrid_enabled_global
```
Database default: 1

### RCCONF_sendgrid_enabled_by_super_users_only 
```
RCCONF_sendgrid_enabled_by_super_users_only 
```
Database default: 0

### RCCONF_sendgrid_display_info_project_setup
```
RCCONF_sendgrid_display_info_project_setup
```
Database default: 0

### RCCONF_two_factor_auth_esign_pin
```
RCCONF_two_factor_auth_esign_pin
```
Database default: `0`

### RCCONF_esignature_enabled_global
```
RCCONF_esignature_enabled_global
```
Database default: `1`

### RCCONF_openid_connect_name
```
RCCONF_openid_connect_name
```
Database default: ``

### RCCONF_openid_connect_primary_admin 
```
RCCONF_openid_connect_primary_admin 
```
Database default: ``

### RCCONF_openid_connect_secondary_admin 
```
RCCONF_openid_connect_secondary_admin 
```
Database default: ``

### RCCONF_openid_connect_provider_url
```
RCCONF_openid_connect_provider_url
```
Database default: ``

### RCCONF_openid_connect_metadata_url
```
RCCONF_openid_connect_metadata_url
```
Database default: ``

### RCCONF_openid_connect_client_id 
```
RCCONF_openid_connect_client_id 
```
Database default: ``

### RCCONF_openid_connect_client_secret 
```
RCCONF_openid_connect_client_secret 
```
Database default: ``

### RCCONF_database_query_tool_enabled
```
RCCONF_database_query_tool_enabled
```
Database default: `0`

### RCCONF_amazon_s3_endpoint_url 
```
RCCONF_amazon_s3_endpoint_url 
```
Database default: ``

### RCCONF_new_form_default_prod_user_access
```
RCCONF_new_form_default_prod_user_access
```
Database default: `1`

### RCCONF_file_upload_vault_filesystem_authtype
```
RCCONF_file_upload_vault_filesystem_authtype
```
Database default: `AUTH_DIGEST`

### RCCONF_pdf_econsent_filesystem_authtype 
```
RCCONF_pdf_econsent_filesystem_authtype 
```
Database default: `AUTH_DIGEST`

### RCCONF_record_locking_pdf_vault_filesystem_authtype 
```
RCCONF_record_locking_pdf_vault_filesystem_authtype 
```
Database default: `AUTH_DIGEST`

### RCCONF_config_settings_key
```
RCCONF_config_settings_key
```
Database default: ``

### RCCONF_oauth2_azure_ad_username_attribute 
```
RCCONF_oauth2_azure_ad_username_attribute 
```
Database default: `userPrincipalName`

### RCCONF_oauth2_azure_ad_endpoint_version 
```
RCCONF_oauth2_azure_ad_endpoint_version 
```
Database default: `V1`

### RCCONF_pdf_econsent_filesystem_container
```
RCCONF_pdf_econsent_filesystem_container
```
Database default: ``

### RCCONF_record_locking_pdf_vault_filesystem_container
```
RCCONF_record_locking_pdf_vault_filesystem_container
```
Database default: ``

### RCCONF_file_upload_vault_filesystem_container 
```
RCCONF_file_upload_vault_filesystem_container 
```
Database default: ``

### RCCONF_google_cloud_storage_api_bucket_name 
```
RCCONF_google_cloud_storage_api_bucket_name 
```
Database default: ``

### RCCONF_google_cloud_storage_api_project_id
```
RCCONF_google_cloud_storage_api_project_id
```
Database default: ``

### RCCONF_google_cloud_storage_api_service_account 
```
RCCONF_google_cloud_storage_api_service_account 
```
Database default: ``

### RCCONF_google_cloud_storage_api_use_project_subfolder 
```
RCCONF_google_cloud_storage_api_use_project_subfolder 
```
Database default: `1`

### RCCONF_override_system_bundle_ca
```
RCCONF_override_system_bundle_ca
```
Database default: `1`

### RCCONF_fhir_break_the_glass_department_type 
```
RCCONF_fhir_break_the_glass_department_type 
```
Database default: ``

### RCCONF_fhir_break_the_glass_patient_type
```
RCCONF_fhir_break_the_glass_patient_type
```
Database default: ``

### RCCONF_email_logging_enable_global
```
RCCONF_email_logging_enable_global
```
Database default: `1`

### RCCONF_email_logging_install_time 
```
RCCONF_email_logging_install_time 
```
Database default: now()

### RCCONF_protected_email_mode_global
```
RCCONF_protected_email_mode_global
```
Database default: `1`

### RCCONF_password_length
```
RCCONF_password_length
```
Database default: `9`

### RCCONF_password_complexity
```
RCCONF_password_complexity
```
Database default: `1`

### RCCONF_reports_allow_public 
```
RCCONF_reports_allow_public 
```
Database default: `1`

### RCCONF_mailgun_api_key
```
RCCONF_mailgun_api_key
```
Database default: ``

### RCCONF_mailgun_domain_name
```
RCCONF_mailgun_domain_name
```
Database default: ``

### RCCONF_db_binlog_format 
```
RCCONF_db_binlog_format 
```
Database default: ``

### RCCONF_default_csv_delimiter
```
RCCONF_default_csv_delimiter
```
Database default: `,`

### RCCONF_project_dashboard_allow_public 
```
RCCONF_project_dashboard_allow_public 
```
Database default: `1`

### RCCONF_project_dashboard_min_data_points
```
RCCONF_project_dashboard_min_data_points
```
Database default: `5`

### RCCONF_oauth2_azure_ad_client_id
```
RCCONF_oauth2_azure_ad_client_id
```
Database default: ``

### RCCONF_oauth2_azure_ad_client_secret
```
RCCONF_oauth2_azure_ad_client_secret
```
Database default: ``

### RCCONF_oauth2_azure_ad_primary_admin
```
RCCONF_oauth2_azure_ad_primary_admin
```
Database default: ``

### RCCONF_oauth2_azure_ad_secondary_admin
```
RCCONF_oauth2_azure_ad_secondary_admin
```
Database default: ``

### RCCONF_fhir_cdp_allow_auto_adjudication 
```
RCCONF_fhir_cdp_allow_auto_adjudication 
```
Database default: `1`

### RCCONF_field_bank_enabled 
```
RCCONF_field_bank_enabled 
```
Database default: `1`

### RCCONF_sendgrid_api_key 
```
RCCONF_sendgrid_api_key 
```
Database default: ``

### RCCONF_fhir_break_the_glass_enabled 
```
RCCONF_fhir_break_the_glass_enabled 
```
Database default: ``

### RCCONF_fhir_break_the_glass_ehr_usertype
```
RCCONF_fhir_break_the_glass_ehr_usertype
```
Database default: `SystemLogin`

### RCCONF_fhir_break_the_glass_token_usertype
```
RCCONF_fhir_break_the_glass_token_usertype
```
Database default: `EMP`

### RCCONF_fhir_break_the_glass_token_username
```
RCCONF_fhir_break_the_glass_token_username
```
Database default: ``

### RCCONF_fhir_break_the_glass_token_password
```
RCCONF_fhir_break_the_glass_token_password
```
Database default: ``

### RCCONF_fhir_break_the_glass_username_token_base_url 
```
RCCONF_fhir_break_the_glass_username_token_base_url 
```
Database default: ``

### RCCONF_record_locking_pdf_vault_filesystem_type 
```
RCCONF_record_locking_pdf_vault_filesystem_type 
```
Database default: ``

### RCCONF_record_locking_pdf_vault_filesystem_host 
```
RCCONF_record_locking_pdf_vault_filesystem_host 
```
Database default: ``

### RCCONF_record_locking_pdf_vault_filesystem_username 
```
RCCONF_record_locking_pdf_vault_filesystem_username 
```
Database default: ``

### RCCONF_record_locking_pdf_vault_filesystem_password 
```
RCCONF_record_locking_pdf_vault_filesystem_password 
```
Database default: ``

### RCCONF_record_locking_pdf_vault_filesystem_path 
```
RCCONF_record_locking_pdf_vault_filesystem_path 
```
Database default: ``

### RCCONF_record_locking_pdf_vault_filesystem_private_key_path 
```
RCCONF_record_locking_pdf_vault_filesystem_private_key_path 
```
Database default: ``

### RCCONF_mandrill_api_key 
```
RCCONF_mandrill_api_key 
```
Database default: ``

### RCCONF_shibboleth_table_config
```
RCCONF_shibboleth_table_config
```
Database default: `{\"splash_default\":\"non-inst-login\",\"table_login_option\":\"Use local REDCap login\",\"institutions\":[{\"login_option\":\"Shibboleth Login\",\"login_text\":\"Click the image below to login using Shibboleth\",\"login_image\":\"https:\/\/wiki.shibboleth.net\/confluence\/download\/attachments\/131074\/atl.site.logo?version=2&modificationDate=1502412080059&api=v2\",\"login_url\":\"\"}]}`

### RCCONF_survey_pid_create_project
```
RCCONF_survey_pid_create_project
```
Database default: ``

### RCCONF_survey_pid_move_to_prod_status 
```
RCCONF_survey_pid_move_to_prod_status 
```
Database default: ``

### RCCONF_survey_pid_move_to_analysis_status 
```
RCCONF_survey_pid_move_to_analysis_status 
```
Database default: ``

### RCCONF_survey_pid_mark_completed
```
RCCONF_survey_pid_mark_completed
```
Database default: ``

### RCCONF_email_alerts_converter_enabled 
```
RCCONF_email_alerts_converter_enabled 
```
Database default: `0`

### RCCONF_use_email_display_name 
```
RCCONF_use_email_display_name 
```
Database default: `1`

### RCCONF_alerts_allow_phone_variables 
```
RCCONF_alerts_allow_phone_variables 
```
Database default: `1`

### RCCONF_alerts_allow_phone_freeform
```
RCCONF_alerts_allow_phone_freeform
```
Database default: `1`

### RCCONF_fhir_standalone_authentication_flow
```
RCCONF_fhir_standalone_authentication_flow
```
Database default: `standalone_launch`

### RCCONF_external_modules_allow_activation_user_request 
```
RCCONF_external_modules_allow_activation_user_request 
```
Database default: `1`

### RCCONF_dkim_private_key 
```
RCCONF_dkim_private_key 
```
Database default: ``

### RCCONF_enable_url_shortener_redcap
```
RCCONF_enable_url_shortener_redcap
```
Database default: `1`

### RCCONF_from_email_domain_exclude
```
RCCONF_from_email_domain_exclude
```
Database default: ``

### RCCONF_fhir_include_email_address 
```
RCCONF_fhir_include_email_address 
```
Database default: `0`

### RCCONF_file_upload_vault_filesystem_type
```
RCCONF_file_upload_vault_filesystem_type
```
Database default: ``

### RCCONF_file_upload_vault_filesystem_host
```
RCCONF_file_upload_vault_filesystem_host
```
Database default: ``

### RCCONF_file_upload_vault_filesystem_username
```
RCCONF_file_upload_vault_filesystem_username
```
Database default: ``

### RCCONF_file_upload_vault_filesystem_password
```
RCCONF_file_upload_vault_filesystem_password
```
Database default: ``

### RCCONF_file_upload_vault_filesystem_path
```
RCCONF_file_upload_vault_filesystem_path
```
Database default: ``

### RCCONF_file_upload_vault_filesystem_private_key_path
```
RCCONF_file_upload_vault_filesystem_private_key_path
```
Database default: ``

### RCCONF_file_upload_versioning_enabled 
```
RCCONF_file_upload_versioning_enabled 
```
Database default: `1`

### RCCONF_file_upload_versioning_global_enabled
```
RCCONF_file_upload_versioning_global_enabled
```
Database default: `1`

### RCCONF_allow_outbound_http
```
RCCONF_allow_outbound_http
```
Database default: `1`

### RCCONF_drw_upload_option_enabled
```
RCCONF_drw_upload_option_enabled
```
Database default: `1`

### RCCONF_pdf_econsent_system_custom_text
```
RCCONF_pdf_econsent_system_custom_text
```
Database default: ``

### RCCONF_alerts_email_freeform_domain_allowlist 
```
RCCONF_alerts_email_freeform_domain_allowlist 
```
Database default: ``

### RCCONF_alerts_allow_email_variables 
```
RCCONF_alerts_allow_email_variables 
```
Database default: `1`

### RCCONF_alerts_allow_email_freeform
```
RCCONF_alerts_allow_email_freeform
```
Database default: `1`

### RCCONF_azure_quickstart 
```
RCCONF_azure_quickstart 
```
Database default: `0`

### RCCONF_google_recaptcha_site_key
```
RCCONF_google_recaptcha_site_key
```
Database default: ``

### RCCONF_google_recaptcha_secret_key
```
RCCONF_google_recaptcha_secret_key
```
Database default: ``

### RCCONF_aws_quickstart 
```
RCCONF_aws_quickstart 
```
Database default: `0`

### RCCONF_user_messaging_prevent_admin_messaging 
```
RCCONF_user_messaging_prevent_admin_messaging 
```
Database default: `0`

### RCCONF_homepage_announcement_login
```
RCCONF_homepage_announcement_login
```
Database default: `1`

### RCCONF_azure_app_name 
```
RCCONF_azure_app_name 
```
Database default: ``

### RCCONF_azure_app_secret 
```
RCCONF_azure_app_secret 
```
Database default: ``

### RCCONF_azure_container
```
RCCONF_azure_container
```
Database default: ``

### RCCONF_redcap_updates_community_user
```
RCCONF_redcap_updates_community_user
```
Database default: ``

### RCCONF_redcap_updates_community_password
```
RCCONF_redcap_updates_community_password
```
Database default: ``

### RCCONF_redcap_updates_user
```
RCCONF_redcap_updates_user
```
Database default: ``

### RCCONF_redcap_updates_password
```
RCCONF_redcap_updates_password
```
Database default: ``

### RCCONF_redcap_updates_password_encrypted
```
RCCONF_redcap_updates_password_encrypted
```
Database default: `1`

### RCCONF_redcap_updates_available 
```
RCCONF_redcap_updates_available 
```
Database default: ``

### RCCONF_redcap_updates_available_last_check
```
RCCONF_redcap_updates_available_last_check
```
Database default: ``

### RCCONF_realtime_webservice_convert_timestamp_from_gmt 
```
RCCONF_realtime_webservice_convert_timestamp_from_gmt 
```
Database default: `0`

### RCCONF_fhir_convert_timestamp_from_gmt
```
RCCONF_fhir_convert_timestamp_from_gmt
```
Database default: `0`

### RCCONF_db_collation 
```
RCCONF_db_collation 
```
Database default: `utf8mb4_unicode_ci`

### RCCONF_db_character_set 
```
RCCONF_db_character_set 
```
Database default: `utf8mb4`

### RCCONF_external_modules_updates_available 
```
RCCONF_external_modules_updates_available 
```
Database default: ``

### RCCONF_external_modules_updates_available_last_check
```
RCCONF_external_modules_updates_available_last_check
```
Database default: ``

### RCCONF_pdf_econsent_system_ip 
```
RCCONF_pdf_econsent_system_ip 
```
Database default: `1`

### RCCONF_pdf_econsent_filesystem_type 
```
RCCONF_pdf_econsent_filesystem_type 
```
Database default: ``

### RCCONF_pdf_econsent_filesystem_host 
```
RCCONF_pdf_econsent_filesystem_host 
```
Database default: ``

### RCCONF_pdf_econsent_filesystem_username 
```
RCCONF_pdf_econsent_filesystem_username 
```
Database default: ``

### RCCONF_pdf_econsent_filesystem_password 
```
RCCONF_pdf_econsent_filesystem_password 
```
Database default: ``

### RCCONF_pdf_econsent_filesystem_path 
```
RCCONF_pdf_econsent_filesystem_path 
```
Database default: ``

### RCCONF_pdf_econsent_filesystem_private_key_path 
```
RCCONF_pdf_econsent_filesystem_private_key_path 
```
Database default: ``

### RCCONF_pdf_econsent_system_enabled
```
RCCONF_pdf_econsent_system_enabled
```
Database default: `1`

### RCCONF_enable_edit_prod_repeating_setup 
```
RCCONF_enable_edit_prod_repeating_setup 
```
Database default: `1`

### RCCONF_user_sponsor_set_expiration_days 
```
RCCONF_user_sponsor_set_expiration_days 
```
Database default: `365`

### RCCONF_user_sponsor_dashboard_enable
```
RCCONF_user_sponsor_dashboard_enable
```
Database default: `1`

### RCCONF_clickjacking_prevention
```
RCCONF_clickjacking_prevention
```
Database default: `0`

### RCCONF_external_module_alt_paths
```
RCCONF_external_module_alt_paths
```
Database default: ``

### RCCONF_aafAccessUrl 
```
RCCONF_aafAccessUrl 
```
Database default: ``

### RCCONF_aafAllowLocalsCreateDB 
```
RCCONF_aafAllowLocalsCreateDB 
```
Database default: ``

### RCCONF_aafAud 
```
RCCONF_aafAud 
```
Database default: ``

### RCCONF_aafDisplayOnEmailUsers 
```
RCCONF_aafDisplayOnEmailUsers 
```
Database default: ``

### RCCONF_aafIss 
```
RCCONF_aafIss 
```
Database default: ``

### RCCONF_aafPrimaryField
```
RCCONF_aafPrimaryField
```
Database default: ``

### RCCONF_aafScopeTarget 
```
RCCONF_aafScopeTarget 
```
Database default: ``

### RCCONF_external_modules_project_custom_text 
```
RCCONF_external_modules_project_custom_text 
```
Database default: ``

### RCCONF_is_development_server
```
RCCONF_is_development_server
```
Database default: `0`

### RCCONF_fhir_data_mart_create_project
```
RCCONF_fhir_data_mart_create_project
```
Database default: `0`

### RCCONF_fhir_data_fetch_interval 
```
RCCONF_fhir_data_fetch_interval 
```
Database default: `24`

### RCCONF_fhir_url_user_access 
```
RCCONF_fhir_url_user_access 
```
Database default: ``

### RCCONF_fhir_custom_text 
```
RCCONF_fhir_custom_text 
```
Database default: ``

### RCCONF_fhir_display_info_project_setup
```
RCCONF_fhir_display_info_project_setup
```
Database default: `1`

### RCCONF_fhir_source_system_custom_name 
```
RCCONF_fhir_source_system_custom_name 
```
Database default: `EHR`

### RCCONF_fhir_user_rights_super_users_only
```
RCCONF_fhir_user_rights_super_users_only
```
Database default: `1`

### RCCONF_fhir_stop_fetch_inactivity_days
```
RCCONF_fhir_stop_fetch_inactivity_days
```
Database default: `7`

### RCCONF_fhir_ddp_enabled 
```
RCCONF_fhir_ddp_enabled 
```
Database default: `0`

### RCCONF_api_token_request_type 
```
RCCONF_api_token_request_type 
```
Database default: `admin_approve`

### RCCONF_fhir_endpoint_authorize_url
```
RCCONF_fhir_endpoint_authorize_url
```
Database default: ``

### RCCONF_fhir_endpoint_token_url
```
RCCONF_fhir_endpoint_token_url
```
Database default: ``

### RCCONF_fhir_ehr_mrn_identifier
```
RCCONF_fhir_ehr_mrn_identifier
```
Database default: ``

### RCCONF_fhir_identity_provider 
```
RCCONF_fhir_identity_provider 
```
Database default: ``

### RCCONF_fhir_client_id 
```
RCCONF_fhir_client_id 
```
Database default: ``

### RCCONF_fhir_client_secret 
```
RCCONF_fhir_client_secret 
```
Database default: ``

### RCCONF_fhir_endpoint_base_url 
```
RCCONF_fhir_endpoint_base_url 
```
Database default: ``

### RCCONF_report_stats_url 
```
RCCONF_report_stats_url 
```
Database default: ``

### RCCONF_user_messaging_enabled 
```
RCCONF_user_messaging_enabled 
```
Database default: `1`

### RCCONF_auto_prod_changes_check_identifiers
```
RCCONF_auto_prod_changes_check_identifiers
```
Database default: `0`

### RCCONF_bioportal_api_url
```
RCCONF_bioportal_api_url
```
Database default: `https://data.bioontology.org/`

### RCCONF_send_emails_admin_tasks
```
RCCONF_send_emails_admin_tasks
```
Database default: `1`

### RCCONF_display_project_xml_backup_option
```
RCCONF_display_project_xml_backup_option
```
Database default: `1`

### RCCONF_cross_domain_access_control
```
RCCONF_cross_domain_access_control
```
Database default: ``

### RCCONF_google_cloud_storage_edocs_bucket
```
RCCONF_google_cloud_storage_edocs_bucket
```
Database default: ``

### RCCONF_google_cloud_storage_temp_bucket 
```
RCCONF_google_cloud_storage_temp_bucket 
```
Database default: ``

### RCCONF_amazon_s3_endpoint 
```
RCCONF_amazon_s3_endpoint 
```
Database default: ``

### RCCONF_proxy_username_password
```
RCCONF_proxy_username_password
```
Database default: ``

### RCCONF_homepage_contact_url 
```
RCCONF_homepage_contact_url 
```
Database default: ``

### RCCONF_bioportal_api_token
```
RCCONF_bioportal_api_token
```
Database default: ``

### RCCONF_two_factor_auth_ip_range_alt 
```
RCCONF_two_factor_auth_ip_range_alt 
```
Database default: ``

### RCCONF_two_factor_auth_trust_period_days_alt
```
RCCONF_two_factor_auth_trust_period_days_alt
```
Database default: `0`

### RCCONF_two_factor_auth_trust_period_days
```
RCCONF_two_factor_auth_trust_period_days
```
Database default: `0`

### RCCONF_two_factor_auth_email_enabled
```
RCCONF_two_factor_auth_email_enabled
```
Database default: `1`

### RCCONF_two_factor_auth_authenticator_enabled
```
RCCONF_two_factor_auth_authenticator_enabled
```
Database default: `1`

### RCCONF_two_factor_auth_ip_check_enabled 
```
RCCONF_two_factor_auth_ip_check_enabled 
```
Database default: `0`

### RCCONF_two_factor_auth_ip_range 
```
RCCONF_two_factor_auth_ip_range 
```
Database default: ``

### RCCONF_two_factor_auth_ip_range_include_private 
```
RCCONF_two_factor_auth_ip_range_include_private 
```
Database default: `0`

### RCCONF_two_factor_auth_duo_enabled
```
RCCONF_two_factor_auth_duo_enabled
```
Database default: `0`

### RCCONF_two_factor_auth_duo_ikey 
```
RCCONF_two_factor_auth_duo_ikey 
```
Database default: ``

### RCCONF_two_factor_auth_duo_skey 
```
RCCONF_two_factor_auth_duo_skey 
```
Database default: ``

### RCCONF_two_factor_auth_duo_hostname 
```
RCCONF_two_factor_auth_duo_hostname 
```
Database default: ``

### RCCONF_bioportal_ontology_list_cache_time 
```
RCCONF_bioportal_ontology_list_cache_time 
```
Database default: ``

### RCCONF_bioportal_ontology_list
```
RCCONF_bioportal_ontology_list
```
Database default: ``

### RCCONF_redcap_survey_base_url 
```
RCCONF_redcap_survey_base_url 
```
Database default: ``

### RCCONF_enable_ontology_auto_suggest 
```
RCCONF_enable_ontology_auto_suggest 
```
Database default: `1`

### RCCONF_enable_survey_text_to_speech 
```
RCCONF_enable_survey_text_to_speech 
```
Database default: `1`

### RCCONF_enable_field_attachment_video_url
```
RCCONF_enable_field_attachment_video_url
```
Database default: `1`

### RCCONF_google_oauth2_client_id
```
RCCONF_google_oauth2_client_id
```
Database default: ``

### RCCONF_google_oauth2_client_secret
```
RCCONF_google_oauth2_client_secret
```
Database default: ``

### RCCONF_two_factor_auth_twilio_enabled 
```
RCCONF_two_factor_auth_twilio_enabled 
```
Database default: `0`

### RCCONF_two_factor_auth_twilio_account_sid 
```
RCCONF_two_factor_auth_twilio_account_sid 
```
Database default: ``

### RCCONF_two_factor_auth_twilio_auth_token
```
RCCONF_two_factor_auth_twilio_auth_token
```
Database default: ``

### RCCONF_two_factor_auth_twilio_from_number 
```
RCCONF_two_factor_auth_twilio_from_number 
```
Database default: ``

### RCCONF_two_factor_auth_twilio_from_number_voice_alt 
```
RCCONF_two_factor_auth_twilio_from_number_voice_alt 
```
Database default: ``

### RCCONF_two_factor_auth_enabled
```
RCCONF_two_factor_auth_enabled
```
Database default: `0`

### RCCONF_allow_kill_mysql_process 
```
RCCONF_allow_kill_mysql_process 
```
Database default: `0`

### RCCONF_mobile_app_enabled 
```
RCCONF_mobile_app_enabled 
```
Database default: `1`

### RCCONF_mycap_enabled_global 
```
RCCONF_mycap_enabled_global 
```
Database default: `1`

### RCCONF_mycap_enable_type
```
RCCONF_mycap_enable_type
```
Database default: `admin`

### RCCONF_twilio_display_info_project_setup
```
RCCONF_twilio_display_info_project_setup
```
Database default: `0`

### RCCONF_twilio_enabled_global
```
RCCONF_twilio_enabled_global
```
Database default: `1`

### RCCONF_twilio_enabled_by_super_users_only 
```
RCCONF_twilio_enabled_by_super_users_only 
```
Database default: `0`

### RCCONF_field_comment_log_enabled_default
```
RCCONF_field_comment_log_enabled_default
```
Database default: `1`

### RCCONF_from_email 
```
RCCONF_from_email 
```
Database default: ``

### RCCONF_promis_enabled 
```
RCCONF_promis_enabled 
```
Database default: `1`

### RCCONF_promis_api_base_url
```
RCCONF_promis_api_base_url
```
Database default: `https://www.redcap-cats.org/promis_api/`

### RCCONF_sams_logout
```
RCCONF_sams_logout
```
Database default: ``

### RCCONF_promis_registration_id 
```
RCCONF_promis_registration_id 
```
Database default: ``

### RCCONF_promis_token 
```
RCCONF_promis_token 
```
Database default: ``

### RCCONF_hook_functions_file
```
RCCONF_hook_functions_file
```
Database default: ``

### RCCONF_project_encoding 
```
RCCONF_project_encoding 
```
Database default: ``

### RCCONF_default_datetime_format
```
RCCONF_default_datetime_format
```
Database default: `M/D/Y_12`

Allowed values:
`M-D-Y_24`, `M-D-Y_12`, `M/D/Y_24`, `M/D/Y_12`, `M.D.Y_24`, `M.D.Y_12`, `D-M-Y_24`, `D-M-Y_12`, `D/M/Y_24`, `D/M/Y_12`, `D.M.Y_24`, `D.M.Y_12`, `Y-M-D_24`, `Y-M-D_12`, `Y/M/D_24`, `Y/M/D_12`, `Y.M.D_24`, `Y.M.D_12`

### RCCONF_default_number_format_decimal
```
RCCONF_default_number_format_decimal
```
Database default: `.`

### RCCONF_default_number_format_thousands_sep
```
RCCONF_default_number_format_thousands_sep
```
Database default: `,`

### RCCONF_homepage_announcement
```
RCCONF_homepage_announcement
```
Database default: ``

### RCCONF_password_algo
```
RCCONF_password_algo
```
Database default: `md5`

### RCCONF_password_recovery_custom_text
```
RCCONF_password_recovery_custom_text
```
Database default: ``

### RCCONF_user_access_dashboard_enable 
```
RCCONF_user_access_dashboard_enable 
```
Database default: `1`

### RCCONF_user_access_dashboard_custom_notification
```
RCCONF_user_access_dashboard_custom_notification
```
Database default: ``

### RCCONF_suspend_users_inactive_send_email
```
RCCONF_suspend_users_inactive_send_email
```
Database default: 1

### RCCONF_suspend_users_inactive_days
```
RCCONF_suspend_users_inactive_days
```
Database default: 180

### RCCONF_suspend_users_inactive_type
```
RCCONF_suspend_users_inactive_type
```
Database default: ``

### RCCONF_page_hit_threshold_per_minute
```
RCCONF_page_hit_threshold_per_minute
```
Database default: `600`

### RCCONF_enable_http_compression
```
RCCONF_enable_http_compression
```
Database default: `1`

### RCCONF_realtime_webservice_data_fetch_interval
```
RCCONF_realtime_webservice_data_fetch_interval
```
Database default: `24`

### RCCONF_realtime_webservice_url_metadata 
```
RCCONF_realtime_webservice_url_metadata 
```
Database default: ``

### RCCONF_realtime_webservice_url_data 
```
RCCONF_realtime_webservice_url_data 
```
Database default: ``

### RCCONF_realtime_webservice_url_user_access
```
RCCONF_realtime_webservice_url_user_access
```
Database default: ``

### RCCONF_realtime_webservice_global_enabled 
```
RCCONF_realtime_webservice_global_enabled 
```
Database default: `0`

### RCCONF_realtime_webservice_custom_text
```
RCCONF_realtime_webservice_custom_text
```
Database default: ``

### RCCONF_realtime_webservice_display_info_project_setup 
```
RCCONF_realtime_webservice_display_info_project_setup 
```
Database default: `1`

### RCCONF_realtime_webservice_source_system_custom_name
```
RCCONF_realtime_webservice_source_system_custom_name
```
Database default: ``

### RCCONF_realtime_webservice_user_rights_super_users_only 
```
RCCONF_realtime_webservice_user_rights_super_users_only 
```
Database default: `1`

### RCCONF_realtime_webservice_stop_fetch_inactivity_days 
```
RCCONF_realtime_webservice_stop_fetch_inactivity_days 
```
Database default: `7`

### RCCONF_amazon_s3_key
```
RCCONF_amazon_s3_key
```
Database default: ``

### RCCONF_amazon_s3_secret 
```
RCCONF_amazon_s3_secret 
```
Database default: ``

### RCCONF_amazon_s3_bucket 
```
RCCONF_amazon_s3_bucket 
```
Database default: ``

### RCCONF_system_offline_message 
```
RCCONF_system_offline_message 
```
Database default: ``

### RCCONF_openid_provider_url
```
RCCONF_openid_provider_url
```
Database default: ``

### RCCONF_openid_provider_name 
```
RCCONF_openid_provider_name 
```
Database default: ``

### RCCONF_file_attachment_upload_max 
```
RCCONF_file_attachment_upload_max 
```
Database default: ``

### RCCONF_data_entry_trigger_enabled 
```
RCCONF_data_entry_trigger_enabled 
```
Database default: `1`

### RCCONF_redcap_base_url_display_error_on_mismatch
```
RCCONF_redcap_base_url_display_error_on_mismatch
```
Database default: `1`

### RCCONF_email_domain_allowlist 
```
RCCONF_email_domain_allowlist 
```
Database default: ``

### RCCONF_helpfaq_custom_text
```
RCCONF_helpfaq_custom_text
```
Database default: ``

### RCCONF_randomization_global 
```
RCCONF_randomization_global 
```
Database default: `1`

### RCCONF_login_custom_text
```
RCCONF_login_custom_text
```
Database default: ``

### RCCONF_auto_prod_changes
```
RCCONF_auto_prod_changes
```
Database default: `4`

### RCCONF_enable_edit_prod_events
```
RCCONF_enable_edit_prod_events
```
Database default: `1`

### RCCONF_allow_create_db_default
```
RCCONF_allow_create_db_default
```
Database default: `1`

### RCCONF_api_enabled
```
RCCONF_api_enabled
```
Database default: `1`

### RCCONF_auth_meth_global 
```
RCCONF_auth_meth_global 
```
Database default: `none` valid values: `none`, `table`,`ldap`, `ldap_table`, `shibboleth`, `shibboleth_table`, `openid_google`, `oauth2_azure_ad`, `oauth2_azure_ad_table`, `rsa`, `sams`, `aaf`, `aaf_table`, `openid_connect`, `openid_connect_table`

### RCCONF_auto_report_stats
```
RCCONF_auto_report_stats
```
Database default: `1`

### RCCONF_autologout_timer 
```
RCCONF_autologout_timer 
```
Database default: `30`

### RCCONF_certify_text_create
```
RCCONF_certify_text_create
```
Database default: ``

### RCCONF_certify_text_prod
```
RCCONF_certify_text_prod
```
Database default: ``

### RCCONF_homepage_custom_text 
```
RCCONF_homepage_custom_text 
```
Database default: ``

### RCCONF_dts_enabled_global 
```
RCCONF_dts_enabled_global 
```
Database default: `0`

### RCCONF_display_project_logo_institution 
```
RCCONF_display_project_logo_institution 
```
Database default: `0`

### RCCONF_display_today_now_button 
```
RCCONF_display_today_now_button 
```
Database default: `1`

### RCCONF_edoc_field_option_enabled
```
RCCONF_edoc_field_option_enabled
```
Database default: `1`

### RCCONF_edoc_upload_max
```
RCCONF_edoc_upload_max
```
Database default: ``

### RCCONF_edoc_storage_option
```
RCCONF_edoc_storage_option
```
Database default: `0`

### RCCONF_file_repository_upload_max 
```
RCCONF_file_repository_upload_max 
```
Database default: ``

### RCCONF_file_repository_enabled
```
RCCONF_file_repository_enabled
```
Database default: `1`

### RCCONF_edoc_path
```
RCCONF_edoc_path
```
Database default: ``

### RCCONF_enable_edit_survey_response
```
RCCONF_enable_edit_survey_response
```
Database default: `1`

### RCCONF_enable_plotting
```
RCCONF_enable_plotting
```
Database default: `2`

### RCCONF_enable_plotting_survey_results 
```
RCCONF_enable_plotting_survey_results 
```
Database default: `1`

### RCCONF_enable_projecttype_singlesurvey
```
RCCONF_enable_projecttype_singlesurvey
```
Database default: `1`

### RCCONF_enable_projecttype_forms 
```
RCCONF_enable_projecttype_forms 
```
Database default: `1`

### RCCONF_enable_projecttype_singlesurveyforms 
```
RCCONF_enable_projecttype_singlesurveyforms 
```
Database default: `1`

### RCCONF_enable_url_shortener 
```
RCCONF_enable_url_shortener 
```
Database default: `1`

### RCCONF_enable_user_allowlist
```
RCCONF_enable_user_allowlist
```
Database default: `0`

### RCCONF_logout_fail_limit
```
RCCONF_logout_fail_limit
```
Database default: `5`

### RCCONF_logout_fail_window 
```
RCCONF_logout_fail_window 
```
Database default: `15`

### RCCONF_footer_links 
```
RCCONF_footer_links 
```
Database default: ``

### RCCONF_footer_text
```
RCCONF_footer_text
```
Database default: ``

### RCCONF_google_translate_enabled 
```
RCCONF_google_translate_enabled 
```
Database default: `0`

### RCCONF_googlemap_key
```
RCCONF_googlemap_key
```
Database default: ``

### RCCONF_grant_cite 
```
RCCONF_grant_cite 
```
Database default: ``

### RCCONF_headerlogo 
```
RCCONF_headerlogo 
```
Database default: ``

### RCCONF_homepage_contact 
```
RCCONF_homepage_contact 
```
Database default: ``

### RCCONF_homepage_contact_email 
```
RCCONF_homepage_contact_email 
```
Database default: ``

### RCCONF_homepage_grant_cite
```
RCCONF_homepage_grant_cite
```
Database default: ``

### RCCONF_identifier_keywords
```
RCCONF_identifier_keywords
```
Database default: `name, street, address, city, county, precinct, zip, postal, date, phone, fax, mail, ssn, social security, mrn, dob, dod, medical, record, id, age`

### RCCONF_institution
```
RCCONF_institution
```
Database default: ``

### RCCONF_language_global
```
RCCONF_language_global
```
Database default: `English`

### RCCONF_login_autocomplete_disable 
```
RCCONF_login_autocomplete_disable 
```
Database default: `0`

### RCCONF_login_logo 
```
RCCONF_login_logo 
```
Database default: ``

### RCCONF_my_profile_enable_edit 
```
RCCONF_my_profile_enable_edit 
```
Database default: `1`

### RCCONF_my_profile_enable_primary_email_edit 
```
RCCONF_my_profile_enable_primary_email_edit 
```
Database default: `1`

### RCCONF_password_history_limit 
```
RCCONF_password_history_limit 
```
Database default: `0`

### RCCONF_password_reset_duration
```
RCCONF_password_reset_duration
```
Database default: `0`

### RCCONF_project_contact_email
```
RCCONF_project_contact_email
```
Database default: ``

### RCCONF_project_contact_name 
```
RCCONF_project_contact_name 
```
Database default: ``

### RCCONF_project_language 
```
RCCONF_project_language 
```
Database default: `English`

### RCCONF_proxy_hostname 
```
RCCONF_proxy_hostname 
```
Database default: ``

### RCCONF_pub_matching_enabled 
```
RCCONF_pub_matching_enabled 
```
Database default: `0`

### RCCONF_redcap_base_url
```
RCCONF_redcap_base_url
```
Database default: ``

### RCCONF_pub_matching_emails
```
RCCONF_pub_matching_emails
```
Database default: `0`

### RCCONF_pub_matching_email_days
```
RCCONF_pub_matching_email_days
```
Database default: `7`

### RCCONF_pub_matching_email_limit 
```
RCCONF_pub_matching_email_limit 
```
Database default: `3`

### RCCONF_pub_matching_email_text
```
RCCONF_pub_matching_email_text
```
Database default: ``

### RCCONF_pub_matching_email_subject 
```
RCCONF_pub_matching_email_subject 
```
Database default: ``

### RCCONF_pub_matching_institution 
```
RCCONF_pub_matching_institution 
```
Database default: `Vanderbilt\nMeharry`

### RCCONF_sendit_enabled 
```
RCCONF_sendit_enabled 
```
Database default: `1`

### RCCONF_sendit_upload_max
```
RCCONF_sendit_upload_max
```
Database default: ``

### RCCONF_shared_library_enabled 
```
RCCONF_shared_library_enabled 
```
Database default: `1`

### RCCONF_shibboleth_logout
```
RCCONF_shibboleth_logout
```
Database default: ``

### RCCONF_shibboleth_username_field
```
RCCONF_shibboleth_username_field
```
Database default: `none`

### RCCONF_site_org_type
```
RCCONF_site_org_type
```
Database default: ``

### RCCONF_superusers_only_create_project 
```
RCCONF_superusers_only_create_project 
```
Database default: `0`

### RCCONF_superusers_only_move_to_prod 
```
RCCONF_superusers_only_move_to_prod 
```
Database default: `1`

### RCCONF_system_offline 
```
RCCONF_system_offline 
```
Database default: `0`

### RCCONF_cache_storage_system 
```
RCCONF_cache_storage_system 
```
Database default: `file`

