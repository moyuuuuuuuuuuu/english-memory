import 'dart:async';

import 'package:english_memory/features/sync/presentation/pending_sync_badge.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('hides zero and renders the latest outstanding count', (
    tester,
  ) async {
    final counts = StreamController<int>();
    addTearDown(counts.close);
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: PendingSyncBadge(counts: counts.stream)),
      ),
    );

    counts.add(0);
    await tester.pumpAndSettle();
    expect(find.textContaining('待同步'), findsNothing);

    counts.add(3);
    await tester.pumpAndSettle();
    expect(find.text('待同步 3'), findsOneWidget);
  });
}
