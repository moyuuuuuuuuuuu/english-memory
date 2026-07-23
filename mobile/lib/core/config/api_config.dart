final class ApiConfig {
  ApiConfig._(this.baseUrl);

  final Uri baseUrl;

  factory ApiConfig.parse(String value) {
    final trimmed = value.trim();
    final uri = Uri.tryParse(trimmed);
    if (trimmed.isEmpty ||
        uri == null ||
        !uri.isAbsolute ||
        uri.host.isEmpty ||
        (uri.scheme != 'http' && uri.scheme != 'https') ||
        uri.hasQuery ||
        uri.hasFragment) {
      throw const FormatException('API base URL must be absolute HTTP(S).');
    }

    final normalizedPath = uri.path == '/'
        ? ''
        : uri.path.replaceFirst(RegExp(r'/+$'), '');
    return ApiConfig._(uri.replace(path: normalizedPath));
  }
}
