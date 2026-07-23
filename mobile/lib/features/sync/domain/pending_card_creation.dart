final class PendingCardCreation {
  const PendingCardCreation({
    required this.localId,
    required this.accountId,
    required this.text,
    required this.contentType,
    required this.memoryStyle,
    required this.attemptCount,
  });

  final String localId;
  final int accountId;
  final String text;
  final String contentType;
  final String memoryStyle;
  final int attemptCount;
}
