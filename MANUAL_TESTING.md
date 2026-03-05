# Manual Testing Checklist

## Prerequisites

- WordPress 6.9+ installed
- WordPress AI Client SDK plugin active
- AWS account with Bedrock access enabled
- AWS IAM user with `bedrock:InvokeModel` permission

## Installation

1. [ ] Upload plugin to `wp-content/plugins/ai-provider-for-bedrock/`
2. [ ] Run `composer install` from plugin directory
3. [ ] Activate plugin in WordPress admin
4. [ ] Verify no PHP errors on activation

## Configuration

### Settings Page
1. [ ] Navigate to Settings > Bedrock AI Provider
2. [ ] Verify all three fields are present (Access Key ID, Secret Access Key, Region)
3. [ ] Enter valid AWS credentials and save
4. [ ] Verify settings are persisted after page reload

### PHP Constants Override
1. [ ] Add `BEDROCK_ACCESS_KEY_ID` constant to wp-config.php
2. [ ] Verify settings page shows "Currently overridden by PHP constant" notice
3. [ ] Verify field is disabled when constant is set

## Provider Registration
1. [ ] Verify provider appears in AI Client registry
2. [ ] Verify provider shows as "Claude on Bedrock"
3. [ ] Verify three Claude models are listed

## Text Generation
1. [ ] Send a simple text prompt
2. [ ] Verify response contains generated text
3. [ ] Verify token usage is reported

## Error Handling
1. [ ] Test with invalid credentials - verify clear error message
2. [ ] Test with missing credentials - verify graceful failure
3. [ ] Test with invalid region - verify appropriate error

## Troubleshooting

- **"AWS access key ID and secret access key must be configured"**: Set credentials in Settings or wp-config.php
- **403 errors**: Verify IAM user has `bedrock:InvokeModel` permission
- **Connection errors**: Verify the selected region has Bedrock available
