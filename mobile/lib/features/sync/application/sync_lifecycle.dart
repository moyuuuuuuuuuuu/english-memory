abstract interface class SyncLifecycleGateway {
  Future<void> activate(int accountId);

  Future<void> drain();

  Future<void> onResumed();

  void deactivate();
}

abstract interface class AccountLocalDataGateway {
  Future<void> clearAccount(int accountId);
}
