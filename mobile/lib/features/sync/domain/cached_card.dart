final class CachedCard {
  const CachedCard({
    required this.localId,
    required this.accountId,
    required this.sourceText,
    required this.contentType,
    required this.memoryStyle,
    required this.localStatus,
    this.serverCardId,
    this.serverJobId,
    this.generationStatus,
  });

  final String localId;
  final int accountId;
  final String sourceText;
  final String contentType;
  final String memoryStyle;
  final String localStatus;
  final int? serverCardId;
  final int? serverJobId;
  final String? generationStatus;
}
