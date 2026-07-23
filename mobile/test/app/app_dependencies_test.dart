import 'package:english_memory/app/app_dependencies.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('production reports missing API configuration without a controller', () {
    final dependencies = AppDependencies.production();

    expect(dependencies.controller, isNull);
    expect(dependencies.configurationMessage, contains('API_BASE_URL'));
  });
}
