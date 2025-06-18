// Ensure the modal is always recreated to prevent any styling/display issues
function ensureReceiptModal(orderId, receiptUrl) {
    // Remove existing modal if it exists
    const existingModal = document.getElementById('receiptModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Create new modal HTML
    const modalHTML = `
    <div id="receiptModal" class="modal" style="display: block; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7);">
        <div class="modal-content" style="background-color: #fff; margin: 10% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative;">
            <div style="text-align: center;">
                <div style="font-size: 24px; color: #4CAF50; margin-bottom: 20px;">
                    <i class="fas fa-check-circle" style="font-size: 64px;"></i>
                </div>
                <h2 style="margin: 15px 0; color: #2c3e50; font-size: 28px;">Payment Successful!</h2>
                <p style="font-size: 16px; color: #555; margin-bottom: 25px;">
                    Your order <strong>#${orderId}</strong> has been placed successfully.
                </p>
                <div style="margin: 30px 0;">
                    <a href="${receiptUrl}" target="_blank" 
                       style="display: inline-block; padding: 12px 30px; background-color: #4CAF50; 
                              color: white; text-decoration: none; border-radius: 5px; 
                              font-weight: 600; font-size: 16px; margin: 0 10px 10px 0;
                              transition: background-color 0.3s;">
                        <i class="fas fa-receipt" style="margin-right: 8px;"></i> View Receipt
                    </a>
                    <button onclick="closeReceiptModal()" 
                            style="padding: 12px 25px; background-color: #6c757d; 
                                   color: white; border: none; border-radius: 5px; 
                                   font-weight: 600; font-size: 16px; cursor: pointer;
                                   transition: background-color 0.3s;">
                        Close
                    </button>
                </div>
                <div style="margin-top: 25px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
                    <p style="margin: 0; font-size: 14px; color: #6c757d;">
                        <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                        You can view this receipt anytime from your order history.
                    </p>
                </div>
                <div style="margin-top: 20px;">
                    <a href="customer-orders.php" 
                       style="color: #4CAF50; text-decoration: none; font-weight: 600;">
                        <i class="fas fa-clipboard-list" style="margin-right: 5px;"></i>
                        Track My Orders
                    </a>
                </div>
            </div>
        </div>
    </div>`;
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Prevent body scroll when modal is open
    document.body.style.overflow = 'hidden';
    
    // Add event listener for clicking outside the modal
    document.getElementById('receiptModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeReceiptModal();
        }
    });
}

// Function to show receipt modal
function showReceiptModal(orderId, receiptUrl) {
    // Make sure Font Awesome is loaded
    if (typeof FontAwesome === 'undefined') {
        const fontAwesome = document.createElement('link');
        fontAwesome.rel = 'stylesheet';
        fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
        document.head.appendChild(fontAwesome);
        
        // Wait for Font Awesome to load
        fontAwesome.onload = function() {
            ensureReceiptModal(orderId, receiptUrl);
        };
    } else {
        ensureReceiptModal(orderId, receiptUrl);
    }
    
    // Show the modal
    const modal = document.getElementById('receiptModal');
    modal.style.display = 'block';
    
    // Add event listener for the close button
    const closeBtn = modal.querySelector('.btn-secondary');
    if (closeBtn) {
        closeBtn.onclick = closeReceiptModal;
    }
    
    // Close modal when clicking outside the content
    window.onclick = function(event) {
        if (event.target === modal) {
            closeReceiptModal();
        }
    };
    
    // Add animation class
    setTimeout(() => {
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.style.animation = 'modalFadeIn 0.3s';
        }
    }, 10);
}

// Function to close the receipt modal
function closeReceiptModal() {
    const modal = document.getElementById('receiptModal');
    if (modal) {
        // Re-enable body scroll
        document.body.style.overflow = 'auto';
        
        // Add fade out animation
        modal.style.opacity = '0';
        modal.style.transition = 'opacity 0.3s ease';
        
        // Remove modal after animation
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// Add CSS for the modal animation
const style = document.createElement('style');
style.textContent = `
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-50px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes modalFadeOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-50px); }
    }
`;
document.head.appendChild(style);

// Function to handle successful payment response
function handlePaymentSuccess(response) {
    if (response && response.success) {
        const orderId = response.data.order_id;
        const receiptUrl = response.data.receipt_url || `generate_receipt.php?order_id=${orderId}`;
        showReceiptModal(orderId, receiptUrl);
    }
}
