<?php
require_once __DIR__ . '/rgb_stream.php';

// The X4 fan lights are wired to the ASRock board's addressable RGB header.
// The X4 HID controller accepts the same direct LED stream for every mode.
const HOMELAB_RGB_PRESETS = [
  'off' => ['label' => 'Off', 'color' => null],
  'white' => ['label' => 'White', 'color' => 'FFFFFF'],
  'warm-white' => ['label' => 'Warm White', 'color' => 'FFD8A8'],
  'red' => ['label' => 'Red', 'color' => 'FF0000'],
  'orange' => ['label' => 'Orange', 'color' => HOMELAB_BRAND_ORANGE],
  'yellow' => ['label' => 'Yellow', 'color' => 'FFFF00'],
  'green' => ['label' => 'Green', 'color' => '00FF00'],
  'teal' => ['label' => 'Teal', 'color' => '00BFA5'],
  'cyan' => ['label' => 'Cyan', 'color' => '00FFFF'],
  'blue' => ['label' => 'Blue', 'color' => HOMELAB_BRAND_BLUE],
  'purple' => ['label' => 'Purple', 'color' => '8000FF'],
  'pink' => ['label' => 'Pink', 'color' => 'FF69B4'],
];
const HOMELAB_RGB_EFFECTS = [
  'rainbow-flow' => ['label' => 'Rainbow Flow', 'effect' => 'synchronized-rainbow'],
  'color-wave' => ['label' => 'Color Wave', 'effect' => 'pinwheel-rainbow'],
  'spectrum-cycle' => ['label' => 'Spectrum Cycle', 'effect' => 'synchronized-rainbow', 'rainbow_cycles' => 2],
];

function homelab_rgb_label($preset)
{
  return HOMELAB_RGB_PRESETS[$preset]['label'] ?? HOMELAB_RGB_EFFECTS[$preset]['label'] ?? null;
}

function homelab_rgb_detect()
{
  $device = homelab_stream_controller();
  if ($device === null) {
    return ['ok' => false, 'output' => '', 'error' => 'Fan lighting controller was not found.'];
  }
  return ['ok' => true, 'output' => $device, 'error' => null];
}

function homelab_rgb_config($preset)
{
  if (!is_string($preset) || homelab_rgb_label($preset) === null) {
    throw new InvalidArgumentException('Choose a listed lighting option.');
  }
  if (isset(HOMELAB_RGB_EFFECTS[$preset])) {
    $effect = HOMELAB_RGB_EFFECTS[$preset];
    $config = ['effect' => $effect['effect']];
    if (isset($effect['rainbow_cycles'])) {
      $config['rainbow_cycles'] = $effect['rainbow_cycles'];
    }
    return $config;
  }
  $color = HOMELAB_RGB_PRESETS[$preset]['color'] ?: '000000';
  return ['top' => $color, 'bottom' => $color];
}

function homelab_rgb_set($preset)
{
  try {
    $config = homelab_rgb_config($preset);
  } catch (InvalidArgumentException $error) {
    return ['ok' => false, 'error' => $error->getMessage()];
  }
  return homelab_stream_start($config);
}

function homelab_rgb_separate_colors($top, $bottom)
{
  $colors = [];
  foreach (['top' => $top, 'bottom' => $bottom] as $fan => $value) {
    if (!is_string($value)) {
      throw new InvalidArgumentException('Choose valid colors for both fans.');
    }
    if (isset(HOMELAB_RGB_PRESETS[$value])) {
      $colors[$fan] = HOMELAB_RGB_PRESETS[$value]['color'] ?: '000000';
    } elseif (preg_match('/^#?[0-9A-Fa-f]{6}$/D', $value)) {
      $colors[$fan] = strtoupper(ltrim($value, '#'));
    } else {
      throw new InvalidArgumentException('Choose valid colors for both fans.');
    }
  }
  return $colors;
}

function homelab_rgb_set_separate($top, $bottom)
{
  try {
    $colors = homelab_rgb_separate_colors($top, $bottom);
  } catch (InvalidArgumentException $error) {
    return ['ok' => false, 'error' => $error->getMessage()];
  }
  return homelab_stream_start($colors);
}

function homelab_rgb_set_stream_effect($effect, $input = [])
{
  if (!is_string($effect) || !isset(HOMELAB_STREAM_EFFECTS[$effect])) {
    return ['ok' => false, 'error' => 'Choose a listed lighting option.'];
  }
  try {
    $tuning = homelab_stream_tuning(array_merge($input, ['effect' => $effect]));
  } catch (InvalidArgumentException $error) {
    return ['ok' => false, 'error' => $error->getMessage()];
  }
  return homelab_stream_start(array_merge(['effect' => $effect], $tuning));
}

function homelab_rgb_set_pinwheel($input = [])
{
  return homelab_rgb_set_stream_effect('pinwheel-rainbow', $input);
}

function homelab_rgb_set_synchronized_rainbow($input = [])
{
  return homelab_rgb_set_stream_effect('synchronized-rainbow', $input);
}

function homelab_rgb_custom_palette_path()
{
  return getenv('HOMELAB_RGB_CUSTOM_PATH') ?: '/boot/config/plugins/45d-drivemap/rgb-custom.json';
}

function homelab_rgb_custom_palette()
{
  $colors = json_decode((string) @file_get_contents(homelab_rgb_custom_palette_path()), true);
  try {
    return homelab_stream_validate_led_colors($colors);
  } catch (InvalidArgumentException $ignored) {
    return array_fill(0, 24, '000000');
  }
}

function homelab_rgb_set_custom($json, $input = [])
{
  if (!is_string($json) || strlen($json) > 512) {
    return ['ok' => false, 'error' => 'Choose valid LED colors.'];
  }
  try {
    $colors = homelab_stream_validate_led_colors(json_decode($json, true));
    $tuning = homelab_stream_tuning($input);
  } catch (InvalidArgumentException $error) {
    return ['ok' => false, 'error' => $error->getMessage()];
  }
  $result = homelab_stream_start(array_merge(['effect' => 'custom-leds', 'leds' => $colors], $tuning));
  if (!$result['ok']) {
    return $result;
  }
  $path = homelab_rgb_custom_palette_path();
  $dir = dirname($path);
  if ((!is_dir($dir) && !mkdir($dir, 0700, true)) ||
      file_put_contents($path . '.tmp', json_encode($colors)) === false ||
      !rename($path . '.tmp', $path)) {
    @unlink($path . '.tmp');
    return ['ok' => false, 'error' => 'Fan colors applied, but could not save the custom palette.'];
  }
  return $result;
}

function homelab_rgb_stream_selection()
{
  $config = json_decode((string) @file_get_contents(homelab_stream_paths()['config']), true);
  if (!is_array($config)) {
    return ['top' => 'orange', 'bottom' => 'blue'];
  }
  $colors = [];
  foreach (['top', 'bottom'] as $fan) {
    $colors[$fan] = $fan === 'top' ? 'orange' : 'blue';
    foreach (HOMELAB_RGB_PRESETS as $name => $preset) {
      if (($preset['color'] ?: '000000') === ($config[$fan] ?? null)) {
        $colors[$fan] = $name;
        break;
      }
    }
  }
  return $colors;
}
