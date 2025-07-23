            </div> <!-- End of main-content -->

        <!-- Add audio element for notifications -->
        <audio id="notificationSound" preload="auto">
            <source src="../assets/sounds/notification.mp3" type="audio/mpeg">
        </audio>

        <script>
            // Function to play notification sound
            function playNotificationSound() {
                console.log('Attempting to play notification sound...');
                const audio = document.getElementById('notificationSound');
                if (!audio) {
                    console.error('Audio element not found!');
                    return;
                }
                
                // Reset the audio to start
                audio.currentTime = 0;
                
                // Try to play the sound
                const playPromise = audio.play();
                
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        console.log('Sound played successfully');
                    }).catch(error => {
                        console.error('Error playing sound:', error);
                        // If autoplay was blocked, you might want to inform the user
                        if (error.name === 'NotAllowedError') {
                            // This is a good place to show a silent notification
                            // For example: add a small icon to the UI asking to enable sound
                        }
                    });
                }
            }

            // Track last message IDs to prevent repeated notifications
            let lastMessageIds = new Set();
            
            // Function to check for new messages
            function checkNewMessages() {
                $.ajax({
                    url: 'check_new_messages.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (data.hasNewMessages && data.unreadCounts.length > 0) {
                            const currentMessageIds = new Set(data.unreadCounts.map(item => item.message_id).filter(id => id));
                            
                            const newMessages = Array.from(currentMessageIds).filter(id => !lastMessageIds.has(id));
                            
                            if (newMessages.length > 0) {
                                playNotificationSound();
                                lastMessageIds = new Set([...lastMessageIds, ...newMessages]);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error checking messages:', error);
                    }
                });
            }

            // Check immediately when page loads
            document.addEventListener('DOMContentLoaded', function() {
                // Initial check for messages
                checkNewMessages();
            });
            
            // Then check every 5 seconds
            setInterval(checkNewMessages, 5000);
        </script>

    </body>
</html>