import 'package:flutter/material.dart';

final class PendingSyncBadge extends StatelessWidget {
  const PendingSyncBadge({this.counts, this.count, super.key})
    : assert(counts != null || count != null);

  final Stream<int>? counts;
  final int? count;

  @override
  Widget build(BuildContext context) {
    final fixedCount = count;
    if (fixedCount != null) return _badge(fixedCount);
    return StreamBuilder<int>(
      stream: counts,
      initialData: 0,
      builder: (context, snapshot) => _badge(snapshot.data ?? 0),
    );
  }

  Widget _badge(int value) {
    if (value < 1) return const SizedBox.shrink();
    return Chip(
      key: const Key('pending_sync_badge'),
      avatar: const Icon(Icons.cloud_upload_outlined, size: 18),
      label: Text('待同步 $value'),
    );
  }
}
