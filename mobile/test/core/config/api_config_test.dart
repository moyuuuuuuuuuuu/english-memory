import 'package:english_memory/core/config/api_config.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('ApiConfig', () {
    test('accepts absolute HTTP and HTTPS URLs', () {
      expect(
        ApiConfig.parse('https://api.example.com/').baseUrl.toString(),
        'https://api.example.com',
      );
      expect(
        ApiConfig.parse('http://10.0.2.2/api/').baseUrl.toString(),
        'http://10.0.2.2/api',
      );
    });

    test('rejects missing, relative, and unsupported URLs', () {
      for (final value in ['', '/api', 'ftp://api.example.com']) {
        expect(() => ApiConfig.parse(value), throwsFormatException);
      }
    });
  });
}
