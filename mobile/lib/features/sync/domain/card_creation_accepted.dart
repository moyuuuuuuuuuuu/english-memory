final class CardCreationAccepted {
  const CardCreationAccepted({
    required this.cardId,
    required this.jobId,
    required this.status,
    required this.replayed,
    required this.dispatchPending,
  });

  final int cardId;
  final int jobId;
  final String status;
  final bool replayed;
  final bool dispatchPending;
}
